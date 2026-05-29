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

class MonoPayGateway implements PaymentGateway
{
    private const API_BASE    = 'https://api.monobank.ua';
    private const API_VERSION = '/api/merchant/invoice';

    public function name(): string
    {
        return 'mono';
    }

    /**
     * Створити рахунок (invoice) в MonoPay і повернути URL оплати.
     * POST /api/merchant/invoice/create
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(CheckoutData $data): string
    {
        $payload = [
            'amount' => $data->amountMinorUnits(),
            'ccy'    => 980, // 980 = UAH (ISO 4217 numeric)
            'merchantPaymInfo' => [
                'reference'   => $data->orderId,
                'destination' => $data->description,
                'basketOrder' => [
                    [
                        'name' => $data->description,
                        'qty'  => 1,
                        'sum'  => $data->amountMinorUnits(),
                        'icon' => '',
                        'unit' => 'послуга',
                        'code' => $data->vacancy ? "vac_{$data->vacancy->id}" : $data->orderId,
                    ],
                ],
            ],
            'redirectUrl' => $data->successUrl,
            'webHookUrl'  => $data->webhookUrl,
            'validity'    => 3600,
            'paymentType' => 'debit',
        ];

        $response = Http::withToken(config('payments.gateways.mono.token'))
            ->post(self::API_BASE . self::API_VERSION . '/create', $payload);

        if ($response->failed()) {
            $errorMessage = $response->json('errText') ?? $response->body();
            Log::channel('payments')->error('MonoPay createCheckout failed', [
                'status' => $response->status(),
                'error'  => $errorMessage,
                'order'  => $data->orderId,
            ]);
            throw new PaymentGatewayException("MonoPay: {$errorMessage}");
        }

        $pageUrl = $response->json('pageUrl');

        if (! $pageUrl) {
            throw new PaymentGatewayException('MonoPay: empty pageUrl in response');
        }

        Log::channel('payments')->info('MonoPay invoice created', [
            'invoice_id' => $response->json('invoiceId'),
            'order_id'   => $data->orderId,
        ]);

        return $pageUrl;
    }

    /**
     * Верифікація Ed25519-підпису та парсинг webhook.
     * Підпис передається у заголовку X-Sign (base64).
     *
     * @throws InvalidWebhookSignatureException
     */
    public function parseWebhook(Request $request): PaymentResult
    {
        $body    = $request->getContent();
        $signB64 = $request->header('X-Sign');

        if (! $signB64) {
            throw new InvalidWebhookSignatureException('MonoPay: missing X-Sign header');
        }

        $this->verifyEd25519Signature($body, $signB64);

        $data = $request->json()->all();

        // MonoPay статуси: success | failure | reversed | created | processing
        $status = $data['status'] ?? '';
        $isPaid = $status === 'success';

        $orderId = $data['reference'] ?? '';
        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        return new PaymentResult(
            isPaid:          $isPaid,
            gatewayName:     $this->name(),
            externalEventId: $data['invoiceId'] ?? uniqid('mono_', true),
            orderId:         $orderId,
            amountKopecks:   (int) ($data['amount'] ?? 0),
            currency:        'UAH',
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
            failureReason:   $isPaid ? null : "status={$status}",
        );
    }

    public function successResponse(): \Illuminate\Http\Response
    {
        return response('');
    }

    // =========================================================================

    /**
     * Верифікація Ed25519-підпису через sodium.
     * Публічний ключ кешується 24 год; при помилці: php artisan cache:forget mono:pubkey
     *
     * @throws InvalidWebhookSignatureException
     */
    private function verifyEd25519Signature(string $body, string $signatureB64): void
    {
        $publicKeyB64 = $this->fetchPublicKey();

        $signature = base64_decode($signatureB64, strict: true);
        $publicKey = base64_decode($publicKeyB64, strict: true);

        if ($signature === false || $publicKey === false) {
            throw new InvalidWebhookSignatureException('MonoPay: failed to decode base64 in X-Sign or public key');
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException(
                'MonoPay webhook verification requires PHP sodium extension. ' .
                'Run: apt install php-sodium'
            );
        }

        try {
            $isValid = sodium_crypto_sign_verify_detached($signature, $body, $publicKey);
        } catch (\SodiumException $e) {
            // Неправильна довжина підпису або ключа
            throw new InvalidWebhookSignatureException('MonoPay: ' . $e->getMessage());
        }

        if (! $isValid) {
            throw new InvalidWebhookSignatureException('MonoPay: Ed25519 signature verification failed');
        }
    }

    /**
     * Повертає публічний ключ MonoPay.
     * Пріоритет: конфіг → кеш (24 год) → API запит.
     *
     * @throws \RuntimeException
     */
    private function fetchPublicKey(): string
    {
        $configured = config('payments.gateways.mono.public_key');
        if ($configured) {
            return $configured;
        }

        return cache()->remember('mono:pubkey', 86400, function () {
            $response = Http::withToken(config('payments.gateways.mono.token'))
                ->get(self::API_BASE . '/api/merchant/pubkey');

            if ($response->failed()) {
                throw new \RuntimeException(
                    'MonoPay: failed to fetch public key: ' . $response->body()
                );
            }

            return $response->json('key');
        });
    }
}
