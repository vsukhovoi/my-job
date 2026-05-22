# МОДУЛЬ 12. MonoPay — інтеграція через Monobank Acquiring API

## 🎯 Мета модуля
Реалізувати `MonoPayGateway`, який дозволяє роботодавцю оплатити продовження вакансії через MonoPay. Webhook верифікується Ed25519-підписом через публічний ключ Monobank.

**Передумова:** модуль 11A виконано (`PaymentGateway` інтерфейс існує).

> **MonoPay — найпопулярніший вибір для українських сервісів у 2024–2025.** Apple Pay / Google Pay з коробки, гарна конверсія, добра документація.

---

## 🔍 КРОК 12.1. Розвідка

```bash
# MonoPay не має офіційного PHP-пакету — HTTP-клієнт є в Laravel
# Перевіримо, що sodium extension доступний (для Ed25519 верифікації)
php -m | grep sodium
php -r "echo function_exists('sodium_crypto_sign_verify_detached') ? 'OK' : 'MISSING';"

# Чи є вже якийсь http-клієнт крім Guzzle?
composer show guzzlehttp/guzzle | grep versions
```

Якщо `sodium` відсутній — потрібно `apt install php-sodium` або `extension=sodium` у `php.ini`. Відзвітуй перед написанням коду.

---

## 📐 Архітектура MonoPay

```
Роботодавець клікає "Оплатити"
       ↓
CheckoutService::createVacancyExtensionCheckout()
       ↓
MonoPayGateway::createCheckout()
  POST https://api.monobank.ua/api/merchant/invoice/create
  → отримуємо { invoiceId, pageUrl }
  → редіректимо на pageUrl
       ↓
Клієнт платить на MonoPay-сторінці
       ↓
MonoPay POST /webhooks/payments/mono
  Headers: X-Sign (Ed25519 base64-підпис)
  Body: { invoiceId, status, amount, ... }
       ↓
MonoPayGateway::parseWebhook()
  → верифікація Ed25519
  → PaymentResult
       ↓
WebhookController → VacancyExtended event
```

---

## 📂 КРОК 12.2. `MonoPayGateway`

`app/Payments/Gateways/MonoPayGateway.php`:

```php
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
     *
     * API: POST /api/merchant/invoice/create
     * Docs: https://api.monobank.ua/docs/acquiring.html
     */
    public function createCheckout(CheckoutData $data): string
    {
        $payload = [
            'amount'      => $data->amountMinorUnits(),       // у копійках
            'ccy'         => 980,                              // 980 = UAH (ISO 4217 numeric)
            'merchantPaymInfo' => [
                'reference'   => $data->orderId,              // ваш orderId — прийде у webhook
                'destination' => $data->description,          // назва у квитанції
                'basketOrder' => [
                    [
                        'name'     => $data->description,
                        'qty'      => 1,
                        'sum'      => $data->amountMinorUnits(),
                        'icon'     => '',
                        'unit'     => 'послуга',
                        'code'     => "vac_{$data->vacancy->id}",
                    ],
                ],
            ],
            'redirectUrl'  => $data->successUrl,
            'webHookUrl'   => $data->webhookUrl,
            'validity'     => 3600,                            // рахунок дійсний 1 год
            'paymentType'  => 'debit',                         // debit = разовий платіж
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

        $invoiceId = $response->json('invoiceId');
        $pageUrl   = $response->json('pageUrl');

        if (! $pageUrl) {
            throw new PaymentGatewayException('MonoPay: empty pageUrl in response');
        }

        Log::channel('payments')->info('MonoPay invoice created', [
            'invoice_id' => $invoiceId,
            'order_id'   => $data->orderId,
        ]);

        return $pageUrl;
    }

    /**
     * Верифікація Ed25519-підпису та парсинг webhook.
     *
     * MonoPay підписує тіло запиту своїм приватним ключем Ed25519.
     * Ми верифікуємо через публічний ключ, отриманий з /api/merchant/pubkey.
     */
    public function parseWebhook(Request $request): PaymentResult
    {
        $body      = $request->getContent();
        $signB64   = $request->header('X-Sign');

        if (! $signB64) {
            throw new InvalidWebhookSignatureException('MonoPay: missing X-Sign header');
        }

        // Верифікація Ed25519
        $this->verifyEd25519Signature($body, $signB64);

        $data = $request->json()->all();

        // MonoPay статуси:
        // success — оплачено; failure — відмова; reversed — повернення
        // created, processing — ще не завершено
        $status = $data['status'] ?? '';
        $isPaid = $status === 'success';

        $orderId = $data['reference'] ?? ''; // ваш orderId з merchantPaymInfo.reference
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
        // MonoPay очікує порожній 200
        return response('');
    }

    // =========================================================================
    // Приватні методи
    // =========================================================================

    /**
     * Верифікація Ed25519-підпису.
     *
     * Публічний ключ отримується з /api/merchant/pubkey або з конфігу.
     * MonoPay рекомендує кешувати ключ і оновлювати при помилці верифікації.
     */
    private function verifyEd25519Signature(string $body, string $signatureB64): void
    {
        $publicKeyB64 = $this->fetchPublicKey();

        try {
            $signature = base64_decode($signatureB64, strict: true);
            $publicKey = base64_decode($publicKeyB64, strict: true);
        } catch (\ValueError $e) {
            throw new InvalidWebhookSignatureException('MonoPay: invalid base64 in X-Sign or public key');
        }

        if ($signature === false || $publicKey === false) {
            throw new InvalidWebhookSignatureException('MonoPay: failed to decode base64');
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException(
                'MonoPay webhook verification requires PHP sodium extension. ' .
                'Run: apt install php-sodium && php artisan down && php artisan up'
            );
        }

        $isValid = sodium_crypto_sign_verify_detached($signature, $body, $publicKey);

        if (! $isValid) {
            throw new InvalidWebhookSignatureException('MonoPay: Ed25519 signature verification failed');
        }
    }

    /**
     * Отримати публічний ключ MonoPay (кешується на 24 год).
     *
     * GET https://api.monobank.ua/api/merchant/pubkey
     */
    private function fetchPublicKey(): string
    {
        // Спочатку з конфіга (якщо вручну виставлено)
        $configured = config('payments.gateways.mono.public_key');
        if ($configured) {
            return $configured;
        }

        // Потім з кешу
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
```

---

## 🔧 КРОК 12.3. Конфігурація `.env`

```ini
# Токен мерчанта — в Dashboard MonoPay → API → Merchant token
MONO_TOKEN=uLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Публічний ключ можна прописати вручну або залишити порожнім
# (тоді буде запитуватись автоматично через /api/merchant/pubkey і кешуватись)
MONO_PUBLIC_KEY=
```

---

## 🧪 КРОК 12.4. Як отримати токен MonoPay

1. Зареєструйся як мерчант: https://web.monobank.ua (особистий → бізнес)
2. Dashboard → Послуги → MonoPay API → Токен для продавця
3. Там само — публічний ключ (можна вставити в `.env` або не вставляти — він підтягнеться автоматично)

**Для тестів:** MonoPay надає тестовий токен. У Dashboard є sandbox-режим.

---

## 🧪 КРОК 12.5. Перевірка

```bash
# 1. Перевір, що gateway реєструється
php artisan tinker --execute="
    \$registry = app(\App\Http\Controllers\Payments\PaymentGatewayRegistry::class);
    dump(\$registry->get('mono') instanceof \App\Payments\Gateways\MonoPayGateway);
"

# 2. Тест створення checkout (потрібен реальний/sandbox токен)
php artisan tinker
```
```php
$vacancy = \App\Models\Vacancy::factory()->active()->create();
$svc = app(\App\Payments\CheckoutService::class);

// Тимчасово встановити mono як активний гейтвей
config(['payments.default' => 'mono']);
$url = $svc->createVacancyExtensionCheckout($vacancy, 30);
echo $url;  // → https://pay.mbnk.biz/xxxxx
```

```bash
# 3. Симуляція webhook (з тестовим підписом MonoPay — з їхньої документації)
# Або через MonoPay Dashboard → Webhooks → Test
```

---

## ⚠️ Критичні нюанси

### 1. Ed25519 — не HMAC
Stripe і WayForPay використовують HMAC-SHA256 (симетрична верифікація). MonoPay — Ed25519 (асиметрична). Це означає:
- Публічний ключ зберігається у тебе (не секрет).
- Приватний ключ — тільки у MonoPay.
- Треба PHP sodium extension.

### 2. Кешування публічного ключа
MonoPay рекомендує кешувати ключ і оновлювати тільки при помилці верифікації. Мій код кешує на 24 год. Якщо верифікація раптом почне падати — флашнути кеш: `php artisan cache:forget mono:pubkey`.

### 3. `reference` = ваш `orderId`
У MonoPay ваш `orderId` передається у `merchantPaymInfo.reference` при створенні і повертається у webhook як `reference`. Саме звідти `parseOrderId` витягує `vacancy_id` і `days`.

### 4. Статус `reversed` — повернення коштів
MonoPay може надіслати webhook зі статусом `reversed` — це означає, що клієнт повернув платіж. У поточній реалізації `isPaid = false` при `reversed`, тобто webhook буде проігнорований. Якщо треба обробляти реверси — додай окремий метод або гілку в `parseWebhook`.

### 5. Retry-логіка MonoPay
MonoPay ретраює webhook до 10 разів із збільшенням інтервалу при 5xx. Повертай **200** на помилки бізнес-логіки (вже є в `WebhookController`). Повертай **400** тільки якщо підпис невалідний.

### 6. Валідність рахунку — `validity: 3600`
Рахунок активний 1 год. Після цього посилання перестає працювати. Якщо потрібно більше — збільши до `86400` (24 год). Але довгі рахунки гірше конвертують.

### 7. Тест-картки MonoPay
| Номер | Результат |
|-------|-----------|
| 4444 1111 1111 1111 | Успішна оплата |
| 4444 2222 2222 2222 | Відмова банку |
| 4444 3333 3333 3333 | 3D-Secure успішно |

---

## ✅ Очікуваний результат

1. `app/Payments/Gateways/MonoPayGateway.php` створено.
2. `config/payments.php` → секція `mono` заповнена.
3. `.env.example` → `MONO_TOKEN`, `MONO_PUBLIC_KEY`.
4. Gateway реєструється і доступний через `PaymentGatewayRegistry::get('mono')`.
5. Тестовий checkout створює реальний invoice URL (sandbox або prod).

Звіт:
```
MonoPayGateway готовий.
Ed25519 верифікація через sodium. Публічний ключ: кешується 24 год.
orderId кодується як reference, розпарсюється в webhook.

Перейти до модуля 13 (WayForPay)? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не зберігай `MONO_TOKEN` у коді — тільки `.env`.
- ❌ Не верифікуй підпис через HMAC — MonoPay використовує Ed25519.
- ❌ Не запитуй публічний ключ при кожному webhook — кешуй на 24 год.
- ❌ Не ігноруй статус `reversed` у продакшені — це означає, що клієнт отримав повернення.
- ❌ Не використовуй `float` для суми — тільки `int` копійок.
