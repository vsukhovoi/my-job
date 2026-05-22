# Telegram Callback Protocol

Документ описує архітектуру обробки Telegram inline-кнопок (callback queries)
в Laravel-частині проєкту My Job.

---

## Формат `callback_data`

Telegram обмежує `callback_data` **64 байтами**. Використовуємо компактний формат:

```
{action}:{resourceType}:{resourceId}:{param}:{signature}
```

| Частина | Тип | Обов'язково | Опис |
|---|---|---|---|
| `action` | string | так | Дія: `respond`, `view`, `approve`, `reject`, ... |
| `resourceType` | string | так | Тип ресурсу: `interview_request`, `application`, ... |
| `resourceId` | int | так | ID запису в БД |
| `param` | string | ні | Додатковий параметр (може бути порожнім рядком) |
| `signature` | string (11 chars) | так | HMAC-підпис (11 символів URL-safe base64) |

### Приклад

```
respond:interview_request:42::aBcD1234efG
```

- action: `respond`
- resourceType: `interview_request`
- resourceId: `42`
- param: `` (порожній)
- signature: `aBcD1234efG`

**Довжина:** `respond:interview_request:42::aBcD1234efG` = 42 байти ✅ (< 64)

---

## HMAC-підпис

### Алгоритм

```php
$base = "{$action}:{$resourceType}:{$resourceId}:{$param}";
$hmac = hash_hmac('sha256', $base, TELEGRAM_CALLBACK_SECRET, binary: true);
$sig  = rtrim(strtr(base64_encode(substr($hmac, 0, 8)), '+/', '-_'), '=');
// Результат: 11 символів URL-safe base64 без padding
```

### Чому 8 байт (не 32)?

- 8 байт HMAC = 2^64 можливих значень → захист від brute-force достатній
- Telegram обмежує callback_data 64 байтами — потрібно економити простір
- 11 символів base64 (ceil(8 * 4/3) = 11) + роздільник `:` = 12 символів на підпис

### Секрет

Змінна `.env`: `TELEGRAM_CALLBACK_SECRET` (окрема від `APP_KEY`)

```php
// config/services.php
'telegram' => [
    'callback_secret' => env('TELEGRAM_CALLBACK_SECRET'),
    'webhook_token'   => env('TELEGRAM_WEBHOOK_TOKEN'),
],
```

---

## Як додати новий callback handler

### Крок 1 — Реалізувати інтерфейс

```php
// app/Telegram/Handlers/MyActionHandler.php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Contracts\TelegramCallbackHandlerInterface;
use App\DataTransferObjects\CallbackDataPayload;
use App\Models\User;

final class MyActionHandler implements TelegramCallbackHandlerInterface
{
    public function canHandle(CallbackDataPayload $payload): bool
    {
        return $payload->action === 'my_action'
            && $payload->resourceType === 'some_resource';
    }

    public function handle(CallbackDataPayload $payload, User $user, int $messageId): void
    {
        $resource = SomeModel::findOrFail($payload->resourceId);

        // бізнес-логіка...

        // Опційно: оновити повідомлення
        app(\App\Services\TelegramNotifier::class)->editMessageText(
            $user->telegram_id,
            $messageId,
            '✅ Виконано!'
        );
    }
}
```

### Крок 2 — Зареєструвати в AppServiceProvider

```php
// app/Providers/AppServiceProvider.php

$this->app->when(\App\Services\Telegram\TelegramCallbackRouter::class)
    ->needs('$handlers')
    ->give([
        new \App\Telegram\Handlers\MyActionHandler(),
        // ... інші handlers
    ]);
```

### Крок 3 — Підписати callback_data при створенні кнопки

```php
$signer = app(\App\Services\Telegram\CallbackDataSigner::class);

$callbackData = $signer->sign(
    action:       'my_action',
    resourceType: 'some_resource',
    resourceId:   $resource->id,
    param:        null, // або додатковий параметр
);

// Передати в TelegramNotifier::sendMessageWithKeyboard()
$inlineKeyboard = [
    [
        ['text' => '🔘 Натисни мене', 'callback_data' => $callbackData],
    ],
];
```

### Крок 4 — Написати тести

```php
#[Test]
public function it_handles_my_action(): void
{
    $signer  = app(CallbackDataSigner::class);
    $payload = $signer->sign('my_action', 'some_resource', 1);

    // mock TelegramNotifier
    // ...

    $router->dispatch($payload, $user, $messageId, 'cq_id_123');

    // assert side effects
}
```

---

## Rate limiting

| Параметр | Значення |
|---|---|
| Ключ Redis | `telegram:callback:rate:{user_id}` |
| Ліміт | 30 callbacks / хвилину на користувача |
| При перевищенні | `answerCallbackQuery` з alert "Забагато запитів. Спробуйте за хвилину." |
| Handler | НЕ виконується |

Реалізовано через `Illuminate\Support\Facades\RateLimiter` (Redis під капотом).

---

## Webhook security model

```
Telegram → Python-бот (callback_query)
    ↓
Python POST /api/telegram/webhook/callback
    Headers: X-Telegram-Webhook-Token: {TELEGRAM_WEBHOOK_TOKEN}
    ↓
TelegramWebhookController::callback()
    1. Перевірити заголовок ≡ config('services.telegram.webhook_token')
       → 401 якщо не збігається
    2. Знайти User за telegram_user_id
       → 404 якщо не знайдено
    3. TelegramCallbackRouter::dispatch(
           callbackData, user, messageId, callbackQueryId
       )
       a. Rate limit check → 429 (через answerCallbackQuery)
       b. HMAC verify → null → лог + answerCallbackQuery("Недійсний запит")
       c. canHandle() → handler->handle()
       d. answerCallbackQuery() завжди (UX)
    4. Return 200 OK
```

### Шари захисту

| Шар | Механізм | Від чого захищає |
|---|---|---|
| Transport | `X-Telegram-Webhook-Token` header | Сторонні POST-запити на webhook |
| Identity | `User::where('telegram_id', ...)` | Підміна telegram_user_id |
| Integrity | HMAC-SHA256 на `callback_data` | Підробка/модифікація callback_data |
| Rate limit | Redis 30/min per user | Flood / replay attacks |

---

## Обмеження Telegram API

| Обмеження | Значення |
|---|---|
| `callback_data` max | 64 байти |
| `answerCallbackQuery` timeout | 10 секунд після отримання callback |
| `editMessageText` window | ~48 годин (після цього — `MessageCantBeEdited`) |
| Inline keyboard buttons per row | 8 (рекомендовано ≤ 3 для мобільних) |

---

## Існуючі actions (станом на Phase 1)

На даний момент handlers ще не реалізовані — це завдання Phase 2+.
Список планованих actions:

| action | resourceType | Опис |
|---|---|---|
| `respond` | `interview_request` | Кандидат відповідає на асинхронну співбесіду |
| `view` | `interview_response` | Роботодавець переглядає відповіді кандидата |
