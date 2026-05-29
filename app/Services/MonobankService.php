<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonobankService
{
    protected string $baseUrl = 'https://corp-api.monobank.ua';
    protected string $token;
    protected string $accountIban;

    public function __construct()
    {
        $this->token       = config('services.monobank_corp.token');
        $this->accountIban = config('services.monobank_corp.account_iban');
    }

    /**
     * Отримати виписку за діапазон часу (Corporate API).
     * Повертає суми в копійках, тільки статус DONE.
     */
    public function getStatements(int $fromTimestamp, int $toTimestamp): array
    {
        $response = Http::withHeaders([
            'x-token' => $this->token,
            'accept'  => 'application/json',
        ])->get("{$this->baseUrl}/ext/v1/statement/{$this->accountIban}/{$fromTimestamp}/{$toTimestamp}");

        if ($response->failed()) {
            Log::error('Monobank statement error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        return array_filter(
            $response->json() ?? [],
            fn(array $item) => ($item['status'] ?? '') === 'DONE'
        );
    }
}
