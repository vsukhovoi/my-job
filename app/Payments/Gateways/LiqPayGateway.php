<?php

declare(strict_types=1);

namespace App\Payments\Gateways;

use App\Payments\CheckoutService;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiqPayGateway implements PaymentGateway
{
    private const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';

    public function name(): string
    {
        return 'liqpay';
    }

    /**
     * Формує GET redirect URL на LiqPay checkout.
     * data = base64(json_encode($params))
     * signature = base64(sha1(private_key + data + private_key))
     */
    public function createCheckout(CheckoutData $data): string
    {
        $params = [
            'public_key'  => config('payments.gateways.liqpay.public_key'),
            'version'     => '3',
            'action'      => 'pay',
            'amount'      => $data->amountUah(),
            'currency'    => $data->currency,
            'description' => $data->description,
            'order_id'    => $data->orderId,
            'result_url'  => $data->successUrl,
            'server_url'  => $data->webhookUrl,
        ];

        $encodedData = $this->encodeData($params);
        $signature   = $this->buildSignature($encodedData);

        Log::channel('payments')->info('LiqPay checkout URL built', ['order_id' => $data->orderId]);

        return self::CHECKOUT_URL . '?' . http_build_query([
            'data'      => $encodedData,
            'signature' => $signature,
        ]);
    }

    /**
     * Верифікація SHA1 та парсинг webhook.
     * Webhook надходить як application/x-www-form-urlencoded (data + signature).
     *
     * @throws InvalidWebhookSignatureException
     */
    public function parseWebhook(Request $request): PaymentResult
    {
        $rawData   = $request->input('data', '');
        $signature = $request->input('signature', '');

        if (! $rawData || ! $signature) {
            throw new InvalidWebhookSignatureException('LiqPay: missing data or signature in webhook body');
        }

        $expectedSignature = $this->buildSignature($rawData);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidWebhookSignatureException('LiqPay: SHA1 signature mismatch');
        }

        $decoded = json_decode(base64_decode($rawData), true);
        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('LiqPay: cannot decode webhook data');
        }

        // LiqPay статуси: success | sandbox | failure | error | reversed | wait_*
        // 'sandbox' = успішна оплата в тестовому режимі
        $status = $decoded['status'] ?? '';
        $isPaid = in_array($status, ['success', 'sandbox'], true);

        $orderId       = $decoded['order_id'] ?? '';
        $amountKopecks = (int) round((float) ($decoded['amount'] ?? 0) * 100);
        $eventId       = isset($decoded['payment_id'])
            ? (string) $decoded['payment_id']
            : uniqid('liqpay_', true);

        Log::channel('payments')->debug('LiqPay webhook received', [
            'status'     => $status,
            'order_id'   => $orderId,
            'payment_id' => $decoded['payment_id'] ?? null,
        ]);

        // Plan subscription order: sub_{userId}_{planId}_{suffix}
        if (str_starts_with($orderId, 'sub_')) {
            [$userId, $planId] = CheckoutService::parseSubscriptionOrderId($orderId);

            return new PaymentResult(
                isPaid:          $isPaid,
                gatewayName:     $this->name(),
                externalEventId: $eventId,
                orderId:         $orderId,
                amountKopecks:   $amountKopecks,
                currency:        $decoded['currency'] ?? 'UAH',
                vacancyId:       null,
                days:            null,
                planId:          $planId,
                userId:          $userId,
                failureReason:   $isPaid ? null : ($decoded['err_description'] ?? "status={$status}"),
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
                currency:             $decoded['currency'] ?? 'UAH',
                vacancyId:            null,
                days:                 null,
                anonymousVacancyId:   $anonymousVacancyId,
                failureReason:        $isPaid ? null : ($decoded['err_description'] ?? "status={$status}"),
            );
        }

        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        return new PaymentResult(
            isPaid:          $isPaid,
            gatewayName:     $this->name(),
            externalEventId: $eventId,
            orderId:         $orderId,
            amountKopecks:   $amountKopecks,
            currency:        $decoded['currency'] ?? 'UAH',
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
            failureReason:   $isPaid ? null : ($decoded['err_description'] ?? "status={$status}"),
        );
    }

    /**
     * LiqPay не вимагає специфічної відповіді — порожній 200.
     */
    public function successResponse(): \Illuminate\Http\Response
    {
        return response('');
    }

    // =========================================================================

    private function encodeData(array $params): string
    {
        return base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * SHA1 підпис: base64(sha1(private_key + data + private_key, raw_output=true))
     * Увага: це НЕ HMAC — просто конкатенація з sha1 raw binary.
     */
    private function buildSignature(string $encodedData): string
    {
        $privateKey = config('payments.gateways.liqpay.private_key');
        return base64_encode(sha1($privateKey . $encodedData . $privateKey, true));
    }
}
