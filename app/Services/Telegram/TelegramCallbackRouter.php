<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramCallbackHandlerInterface;
use App\Models\User;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class TelegramCallbackRouter
{
    private const RATE_LIMIT_KEY    = 'telegram:callback:rate:';
    private const RATE_LIMIT_MAX    = 30;
    private const RATE_LIMIT_DECAY  = 60;

    /** @param  TelegramCallbackHandlerInterface[]  $handlers */
    public function __construct(
        private readonly CallbackDataSigner $signer,
        private readonly TelegramNotifier   $notifier,
        private readonly array              $handlers = [],
    ) {}

    public function dispatch(
        string  $callbackData,
        User    $user,
        int     $messageId,
        ?string $callbackQueryId = null,
    ): void {
        $rateLimitKey = self::RATE_LIMIT_KEY . $user->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            if ($callbackQueryId) {
                $this->notifier->answerCallbackQuery(
                    $callbackQueryId,
                    'Забагато запитів. Спробуйте за хвилину.',
                    showAlert: true,
                );
            }
            return;
        }

        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

        $payload = $this->signer->verify($callbackData);

        if ($payload === null) {
            Log::warning('TelegramCallbackRouter: invalid signature', [
                'user_id' => $user->id,
                'data'    => substr($callbackData, 0, 32),
            ]);

            if ($callbackQueryId) {
                $this->notifier->answerCallbackQuery($callbackQueryId, 'Недійсний запит.');
            }
            return;
        }

        foreach ($this->handlers as $handler) {
            if (! ($handler instanceof TelegramCallbackHandlerInterface)) {
                continue;
            }

            if (! $handler->canHandle($payload)) {
                continue;
            }

            try {
                $handler->handle($payload, $user, $messageId);
                Log::info('TelegramCallbackRouter: dispatched', [
                    'action'       => $payload->action,
                    'resourceType' => $payload->resourceType,
                    'resourceId'   => $payload->resourceId,
                    'user_id'      => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('TelegramCallbackRouter: handler exception', [
                    'handler' => get_class($handler),
                    'error'   => $e->getMessage(),
                ]);
            }

            if ($callbackQueryId) {
                $this->notifier->answerCallbackQuery($callbackQueryId);
            }
            return;
        }

        Log::warning('TelegramCallbackRouter: no handler found', [
            'action'       => $payload->action,
            'resourceType' => $payload->resourceType,
        ]);

        if ($callbackQueryId) {
            $this->notifier->answerCallbackQuery($callbackQueryId);
        }
    }
}
