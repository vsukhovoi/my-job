<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\DataTransferObjects\CallbackDataPayload;

class CallbackDataSigner
{
    private const MAX_BYTES = 64;
    private const SEPARATOR = ':';

    public function sign(
        string  $action,
        string  $resourceType,
        int     $resourceId,
        ?string $param = null,
    ): string {
        $body = implode(self::SEPARATOR, array_filter(
            [$action, $resourceType, (string) $resourceId, $param ?? ''],
            fn ($v) => $v !== '',
        ));

        // param може бути порожнім, але потрібен роздільник для парсингу
        $base = "{$action}:{$resourceType}:{$resourceId}:" . ($param ?? '');
        $sig  = $this->computeSignature($base);
        $result = "{$base}:{$sig}";

        if (strlen($result) > self::MAX_BYTES) {
            throw new \OverflowException(
                "callback_data exceeds 64 bytes: {$result} (" . strlen($result) . ' bytes)'
            );
        }

        return $result;
    }

    public function verify(string $callbackData): ?CallbackDataPayload
    {
        $parts = explode(self::SEPARATOR, $callbackData);

        // Мінімум: action:resourceType:resourceId::signature (5 частин)
        if (count($parts) < 5) {
            return null;
        }

        $sig           = array_pop($parts);
        [$action, $resourceType, $resourceId, $param] = array_pad($parts, 4, '');

        if (! is_numeric($resourceId) || $action === '' || $resourceType === '') {
            return null;
        }

        $base     = implode(self::SEPARATOR, [$action, $resourceType, $resourceId, $param]);
        $expected = $this->computeSignature($base);

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        return new CallbackDataPayload(
            action:       $action,
            resourceType: $resourceType,
            resourceId:   (int) $resourceId,
            param:        $param !== '' ? $param : null,
        );
    }

    private function computeSignature(string $data): string
    {
        $secret = config('services.telegram.callback_secret', config('app.key'));
        $hmac   = hash_hmac('sha256', $data, $secret, binary: true);
        return rtrim(strtr(base64_encode(substr($hmac, 0, 8)), '+/', '-_'), '=');
    }
}
