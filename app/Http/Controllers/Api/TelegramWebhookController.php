<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\TelegramSubscription;
use App\Models\User;
use App\Services\Telegram\TelegramCallbackRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

    /**
     * POST /api/telegram/link
     * Links a Telegram account to an existing user via telegram_link_token.
     */
    public function link(Request $request): JsonResponse
    {
        $token = $request->header('X-Telegram-Webhook-Token');

        if ($token !== config('services.telegram.webhook_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $linkToken      = $request->string('link_token')->toString();
        $telegramUserId = $request->integer('telegram_user_id');

        if (! $linkToken || ! $telegramUserId) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $user = User::where('telegram_link_token', $linkToken)->first();

        if (! $user) {
            Log::warning('TelegramWebhookController::link: token not found', [
                'telegram_user_id' => $telegramUserId,
            ]);
            return response()->json(['error' => 'Invalid or expired token'], 404);
        }

        $user->update([
            'telegram_id'         => $telegramUserId,
            'telegram_link_token' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/telegram/alerts?telegram_user_id={id}
     */
    public function alerts(Request $request): JsonResponse
    {
        if ($request->header('X-Telegram-Webhook-Token') !== config('services.telegram.webhook_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telegramUserId = $request->integer('telegram_user_id');

        if (! $telegramUserId) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $subscribed = TelegramSubscription::where('telegram_id', $telegramUserId)
            ->pluck('category_id')
            ->toArray();

        $categories = Category::orderBy('position')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'subscribed' => in_array($c->id, $subscribed, true),
            ]);

        return response()->json(['categories' => $categories]);
    }

    /**
     * POST /api/telegram/alerts/toggle
     */
    public function alertsToggle(Request $request): JsonResponse
    {
        if ($request->header('X-Telegram-Webhook-Token') !== config('services.telegram.webhook_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telegramUserId = $request->integer('telegram_user_id');
        $categoryId     = $request->integer('category_id');

        if (! $telegramUserId || ! $categoryId) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $category = Category::find($categoryId);

        if (! $category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $existing = TelegramSubscription::where('telegram_id', $telegramUserId)
            ->where('category_id', $categoryId)
            ->first();

        if ($existing) {
            $existing->delete();
            $subscribed = false;
        } else {
            TelegramSubscription::create([
                'telegram_id' => $telegramUserId,
                'category_id' => $categoryId,
            ]);
            $subscribed = true;
        }

        $allSubscribed = TelegramSubscription::where('telegram_id', $telegramUserId)
            ->pluck('category_id')
            ->toArray();

        $categories = Category::orderBy('position')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'subscribed' => in_array($c->id, $allSubscribed, true),
            ]);

        return response()->json([
            'subscribed' => $subscribed,
            'category'   => $category->name,
            'categories' => $categories,
        ]);
    }
}
