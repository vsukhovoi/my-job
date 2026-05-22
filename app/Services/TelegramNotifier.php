<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TelegramMessageResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    private string $botApiUrl;

    public function __construct()
    {
        $this->botApiUrl = config('services.telegram_bot.api_url');
    }

    public function send(string $chatId, string $text): bool
    {
        try {
            $response = Http::timeout(5)->post("{$this->botApiUrl}/send-message", [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramNotifier: failed', [
                    'chat_id' => $chatId,
                    'status'  => $response->status(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramNotifier: exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param  array<array<array{text: string, url?: string, callback_data?: string}>>  $inlineKeyboard
     */
    public function sendMessageWithKeyboard(int $telegramId, string $text, array $inlineKeyboard): TelegramMessageResult
    {
        try {
            $response = Http::timeout(5)->post("{$this->botApiUrl}/send-message-with-keyboard", [
                'chat_id'         => $telegramId,
                'text'            => $text,
                'parse_mode'      => 'HTML',
                'inline_keyboard' => $inlineKeyboard,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramNotifier::sendMessageWithKeyboard failed', [
                    'chat_id' => $telegramId,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                return TelegramMessageResult::fail("HTTP {$response->status()}");
            }

            $data      = $response->json();
            $messageId = (int) ($data['message_id'] ?? 0);
            $chatId    = (int) ($data['chat_id'] ?? $telegramId);

            return TelegramMessageResult::ok($messageId, $chatId);
        } catch (\Throwable $e) {
            Log::error('TelegramNotifier::sendMessageWithKeyboard exception', ['message' => $e->getMessage()]);
            return TelegramMessageResult::fail($e->getMessage());
        }
    }

    /**
     * @param  array<array<array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function editMessageText(int $telegramId, int $messageId, string $newText, ?array $inlineKeyboard = null): bool
    {
        try {
            $payload = [
                'chat_id'    => $telegramId,
                'message_id' => $messageId,
                'text'       => $newText,
                'parse_mode' => 'HTML',
            ];

            if ($inlineKeyboard !== null) {
                $payload['inline_keyboard'] = $inlineKeyboard;
            }

            $response = Http::timeout(5)->post("{$this->botApiUrl}/edit-message", $payload);

            if (! $response->successful()) {
                Log::warning('TelegramNotifier::editMessageText failed', [
                    'chat_id'    => $telegramId,
                    'message_id' => $messageId,
                    'status'     => $response->status(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramNotifier::editMessageText exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        try {
            $response = Http::timeout(5)->post("{$this->botApiUrl}/answer-callback", [
                'callback_query_id' => $callbackQueryId,
                'text'              => $text,
                'show_alert'        => $showAlert,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramNotifier::answerCallbackQuery failed', [
                    'callback_query_id' => $callbackQueryId,
                    'status'            => $response->status(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramNotifier::answerCallbackQuery exception', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
