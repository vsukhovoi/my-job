<?php

declare(strict_types=1);

namespace App\Payments\Gateways;

use App\Payments\CheckoutService;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WayForPayGateway implements PaymentGateway
{
    private const FORM_URL = 'https://secure.wayforpay.com/pay';
    private const API_URL  = 'https://api.wayforpay.com/api';

    public function name(): string
    {
        return 'wayforpay';
    }

    /**
     * Hosted mode: POST на WFP API → invoiceUrl.
     * Form mode: кешує параметри і повертає route для auto-submit сторінки.
     *
     * @throws PaymentGatewayException|\InvalidArgumentException
     */
    public function createCheckout(CheckoutData $data): string
    {
        $mode = config('payments.gateways.wayforpay.checkout_mode', 'form');

        return match ($mode) {
            'hosted' => $this->createHostedCheckout($data),
            'form'   => $this->createFormCheckoutUrl($data),
            default  => throw new \InvalidArgumentException("Unknown WFP checkout mode: {$mode}"),
        };
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $data = $request->json()->all();

        $this->verifyHmacSignature($data);

        // WFP статуси: Approved | Expired | Declined | Refunded | Voided | Pending
        $transactionStatus = $data['transactionStatus'] ?? '';
        $isPaid            = $transactionStatus === 'Approved';

        $orderId       = $data['orderReference'] ?? '';
        $amountKopecks = (int) round((float) ($data['amount'] ?? 0) * 100);
        $eventId       = $orderId ?: uniqid('wfp_', true);

        Log::channel('payments')->debug('WayForPay webhook received', [
            'status'   => $transactionStatus,
            'order_id' => $orderId,
        ]);

        if (str_starts_with($orderId, 'sub_')) {
            [$userId, $planId] = CheckoutService::parseSubscriptionOrderId($orderId);

            return new PaymentResult(
                isPaid:          $isPaid,
                gatewayName:     $this->name(),
                externalEventId: $eventId,
                orderId:         $orderId,
                amountKopecks:   $amountKopecks,
                currency:        $data['currency'] ?? 'UAH',
                vacancyId:       null,
                days:            null,
                planId:          $planId,
                userId:          $userId,
                failureReason:   $isPaid ? null : "status={$transactionStatus}",
            );
        }

        if (str_starts_with($orderId, 'anon_')) {
            $anonymousVacancyId = CheckoutService::parseAnonymousOrderId($orderId);

            return new PaymentResult(
                isPaid:               $isPaid,
                gatewayName:          $this->name(),
                externalEventId:      $eventId,
                orderId:              $orderId,
                amountKopecks:        $amountKopecks,
                currency:             $data['currency'] ?? 'UAH',
                vacancyId:            null,
                days:                 null,
                anonymousVacancyId:   $anonymousVacancyId,
                failureReason:        $isPaid ? null : "status={$transactionStatus}",
            );
        }

        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        return new PaymentResult(
            isPaid:          $isPaid,
            gatewayName:     $this->name(),
            externalEventId: $eventId,
            orderId:         $orderId,
            amountKopecks:   $amountKopecks,
            currency:        $data['currency'] ?? 'UAH',
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
            failureReason:   $isPaid ? null : "status={$transactionStatus}",
        );
    }

    /**
     * WFP очікує підписане JSON-підтвердження, не просто "accept".
     * Без правильного підпису WFP буде ретраїти webhook.
     */
    public function successResponse(): \Illuminate\Http\Response
    {
        $time           = time();
        $orderReference = request()->json('orderReference', '');
        $signature      = $this->buildResponseSignature($orderReference, 'accept', $time);

        return response(json_encode([
            'orderReference' => $orderReference,
            'status'         => 'accept',
            'time'           => $time,
            'signature'      => $signature,
        ]), 200, ['Content-Type' => 'application/json']);
    }

    // =========================================================================

    private function createHostedCheckout(CheckoutData $data): string
    {
        $params = $this->buildFormParams($data);

        $apiParams = array_merge(
            array_diff_key($params, ['signature' => '']),
            ['merchantSignature' => $params['signature']]
        );

        $payload = json_encode(array_merge($apiParams, [
            'transactionType' => 'CREATE_INVOICE',
            'apiVersion'      => 1,
        ]), JSON_UNESCAPED_UNICODE);


        $response = Http::withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->withBody($payload, 'application/json')
            ->post(self::API_URL);

        if ($response->failed()) {
            throw new PaymentGatewayException('WayForPay: API error: ' . $response->body());
        }

        $invoiceUrl = $response->json('invoiceUrl');

        if (! $invoiceUrl) {
            Log::channel('payments')->error('WayForPay createHostedCheckout: no invoiceUrl', [
                'order_id'     => $data->orderId,
                'full_response' => $response->json(),
            ]);
            $reason = $response->json('reason') ?? 'unknown';
            throw new PaymentGatewayException("WayForPay: empty invoiceUrl. Reason: {$reason}");
        }

        Log::channel('payments')->info('WayForPay invoice created (hosted)', [
            'order_id' => $data->orderId,
        ]);

        return $invoiceUrl;
    }

    /**
     * Кешує параметри форми на 10 хв і повертає URL проміжної сторінки.
     * Реальний POST на WFP відбувається через JS auto-submit у Blade-view.
     */
    private function createFormCheckoutUrl(CheckoutData $data): string
    {
        cache()->put('wfp:form:' . $data->orderId, $this->buildFormParams($data), 600);

        return route('payments.wfp.form', ['orderId' => $data->orderId]);
    }

    /**
     * Будує масив параметрів для форми або API запиту.
     *
     * Порядок полів підпису КРИТИЧНИЙ:
     * merchantAccount;merchantDomainName;orderReference;orderDate;amount;currency;
     * productName[0];productCount[0];productPrice[0]
     */
    private function buildFormParams(CheckoutData $data): array
    {
        $orderDate   = time();
        $amountFloat = $data->amountUah();

        $signatureString = implode(';', [
            config('payments.gateways.wayforpay.merchant_account'),
            config('payments.gateways.wayforpay.merchant_domain'),
            $data->orderId,
            $orderDate,
            $amountFloat,
            $data->currency,
            $data->description,
            1,
            $amountFloat,
        ]);

        $signature = hash_hmac(
            'md5',
            $signatureString,
            config('payments.gateways.wayforpay.merchant_password'),
        );

        Log::channel('payments')->debug('WayForPay buildFormParams', [
            'order_id'         => $data->orderId,
            'signature_string' => $signatureString,
            'signature'        => $signature,
            'amount_float'     => $amountFloat,
            'amount_type'      => gettype($amountFloat),
        ]);

        return [
            'merchantAccount'                 => config('payments.gateways.wayforpay.merchant_account'),
            'merchantDomainName'              => config('payments.gateways.wayforpay.merchant_domain'),
            'merchantTransactionSecureType'   => 'AUTO',
            'orderReference'                  => $data->orderId,
            'orderDate'                       => $orderDate,
            'amount'                          => $amountFloat,
            'currency'                        => $data->currency,
            'productName'                     => [$data->description],
            'productCount'                    => [1],
            'productPrice'                    => [$amountFloat],
            'clientEmail'                     => $data->customerEmail ?? '',
            'clientFirstName'                 => $data->customerName  ?? '',
            'serviceUrl'                      => $data->webhookUrl,
            'returnUrl'                       => $data->successUrl,
            'signature'                       => $signature,
        ];
    }

    /**
     * Верифікація HMAC-MD5 підпису вхідного webhook.
     *
     * Порядок полів (з документації WFP):
     * merchantAccount;orderReference;amount;currency;authCode;cardPan;transactionStatus;reasonCode
     */
    private function verifyHmacSignature(array $data): void
    {
        $signatureString = implode(';', [
            $data['merchantAccount']   ?? '',
            $data['orderReference']    ?? '',
            $data['amount']            ?? '',
            $data['currency']          ?? '',
            $data['authCode']          ?? '',
            $data['cardPan']           ?? '',
            $data['transactionStatus'] ?? '',
            $data['reasonCode']        ?? '',
        ]);

        $expected = hash_hmac(
            'md5',
            $signatureString,
            config('payments.gateways.wayforpay.merchant_password'),
        );

        $received = $data['merchantSignature'] ?? '';

        if (! hash_equals($expected, $received)) {
            throw new InvalidWebhookSignatureException(
                "WayForPay: HMAC mismatch. Expected={$expected}, got={$received}"
            );
        }
    }

    private function buildResponseSignature(string $orderReference, string $status, int $time): string
    {
        return hash_hmac(
            'md5',
            implode(';', [$orderReference, $status, $time]),
            config('payments.gateways.wayforpay.merchant_password'),
        );
    }

    // ── P2P виплати ───────────────────────────────────────────────────────────

    /**
     * P2P cardtransfer — виплата на картку отримувача.
     * Використовує окремий мерчант WFP_P2P_ACCOUNT.
     *
     * @throws PaymentGatewayException
     */
    public function p2pCardTransfer(
        string $cardPan,
        float $amount,
        string $currency,
        string $orderReference,
        string $clientEmail = '',
    ): array {
        $merchantAccount = config('payments.gateways.wayforpay.p2p_account');
        $merchantDomain  = config('payments.gateways.wayforpay.merchant_domain');
        $orderDate       = time();

        $signatureString = implode(';', [
            $merchantAccount,
            $merchantDomain,
            $orderReference,
            $orderDate,
            $amount,
            $currency,
            'P2P transfer',
            1,
            $amount,
        ]);

        $signature = hash_hmac('md5', $signatureString, config('payments.gateways.wayforpay.merchant_password'));

        $response = Http::post(self::API_URL, [
            'transactionType'   => 'PURCHASE',
            'merchantAccount'   => $merchantAccount,
            'merchantDomainName'=> $merchantDomain,
            'orderReference'    => $orderReference,
            'orderDate'         => $orderDate,
            'amount'            => $amount,
            'currency'          => $currency,
            'productName'       => ['P2P transfer'],
            'productCount'      => [1],
            'productPrice'      => [$amount],
            'clientEmail'       => $clientEmail,
            'recTokenLifetime'  => 1,
            'paymentSystems'    => 'card',
            'defaultPaymentSystem' => 'card',
            'recToken'          => $cardPan,
            'signature'         => $signature,
        ]);

        if ($response->failed()) {
            throw new PaymentGatewayException('WayForPay p2p cardtransfer error: ' . $response->body());
        }

        Log::channel('payments')->info('WayForPay p2p cardtransfer sent', [
            'order'  => $orderReference,
            'amount' => $amount,
        ]);

        return $response->json();
    }

    /**
     * P2P credit — кредитна виплата на картку.
     * Використовує окремий мерчант WFP_P2P_CREDIT_ACCOUNT.
     *
     * @throws PaymentGatewayException
     */
    public function p2pCredit(
        string $cardPan,
        float $amount,
        string $currency,
        string $orderReference,
        string $clientEmail = '',
    ): array {
        $merchantAccount = config('payments.gateways.wayforpay.p2p_credit_account');
        $merchantDomain  = config('payments.gateways.wayforpay.merchant_domain');
        $orderDate       = time();

        $signatureString = implode(';', [
            $merchantAccount,
            $merchantDomain,
            $orderReference,
            $orderDate,
            $amount,
            $currency,
            'P2P credit',
            1,
            $amount,
        ]);

        $signature = hash_hmac('md5', $signatureString, config('payments.gateways.wayforpay.merchant_password'));

        $response = Http::post(self::API_URL, [
            'transactionType'   => 'CREDIT',
            'merchantAccount'   => $merchantAccount,
            'merchantDomainName'=> $merchantDomain,
            'orderReference'    => $orderReference,
            'orderDate'         => $orderDate,
            'amount'            => $amount,
            'currency'          => $currency,
            'productName'       => ['P2P credit'],
            'productCount'      => [1],
            'productPrice'      => [$amount],
            'clientEmail'       => $clientEmail,
            'recToken'          => $cardPan,
            'signature'         => $signature,
        ]);

        if ($response->failed()) {
            throw new PaymentGatewayException('WayForPay p2p credit error: ' . $response->body());
        }

        Log::channel('payments')->info('WayForPay p2p credit sent', [
            'order'  => $orderReference,
            'amount' => $amount,
        ]);

        return $response->json();
    }
}
