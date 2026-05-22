<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram\TelegramCallbackRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramCallbackRouter $router,
    ) {}

    /**
     * POST /api/telegram/webhook/callback
     */
    public function callback(Request $request): JsonResponse
    {
        $token = $request->header('X-Telegram-Webhook-Token');

        if ($token !== config('services.telegram.webhook_token')) {
            Log::warning('TelegramWebhookController: invalid token', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telegramUserId  = $request->integer('telegram_user_id');
        $callbackData    = $request->string('callback_data')->toString();
        $messageId       = $request->integer('message_id');
        $callbackQueryId = $request->string('callback_query_id')->toString();

        if (! $telegramUserId || ! $callbackData) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $user = User::where('telegram_id', $telegramUserId)->first();

        if (! $user) {
            Log::warning('TelegramWebhookController: user not found', [
                'telegram_user_id' => $telegramUserId,
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        $this->router->dispatch(
            callbackData:    $callbackData,
            user:            $user,
            messageId:       $messageId,
            callbackQueryId: $callbackQueryId ?: null,
        );

        return response()->json(['ok' => true]);
    }
}
