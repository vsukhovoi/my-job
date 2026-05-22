# МОДУЛЬ 14. LiqPay — інтеграція через PrivatBank Acquiring

## 🎯 Мета модуля
Реалізувати `LiqPayGateway`. LiqPay — платіжна система ПриватБанку, одна з найпопулярніших в Україні. Особливості: base64(JSON) + SHA1 підпис, власна кнопка «Сплатити через LiqPay» з готовим SDK, webhook верифікується аналогічно.

**Передумова:** модуль 11A виконано.

> **Важлива особливість LiqPay.** Стандартний флоу — це кнопка, яку LiqPay рендерить через свій JS SDK. Однак для нашого проєкту ми робимо серверний redirect (без SDK), що підтримується офіційно через `action: pay` + server-side form.

---

## 🔍 КРОК 14.1. Розвідка

```bash
# Офіційний пакет LiqPay для PHP
composer show liqpay/liqpay 2>/dev/null && echo "Є" || echo "Відсутній"

# Незалежний пакет (більш сучасний)
composer show greensight/liqpay 2>/dev/null && echo "Є greensight" || echo "Немає"

# Наявний код
find app -type f | xargs grep -l "liqpay\|LiqPay" 2>/dev/null
```

Я реалізую без зовнішніх пакетів — LiqPay API простий і не потребує SDK. Але якщо хочеш встановити офіційний — скажи.

---

## 📐 Архітектура LiqPay

```
Роботодавець клікає "Оплатити LiqPay"
       ↓
CheckoutService → LiqPayGateway::createCheckout()
       ↓
  Будуємо data (base64 JSON) + signature (SHA1)
  Повертаємо URL redirect для POST:
  https://www.liqpay.ua/api/3/checkout?data=...&signature=...
       ↓
Клієнт потрапляє на LiqPay-сторінку
       ↓
LiqPay POST /webhooks/payments/liqpay
  Body: data (base64 JSON) + signature (SHA1)
       ↓
LiqPayGateway::parseWebhook()
  → перевірка SHA1
  → PaymentResult
       ↓
WebhookController → VacancyExtended event
```

---

## 📂 КРОК 14.2. `LiqPayGateway`

`app/Payments/Gateways/LiqPayGateway.php`:

```php
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
    private const API_URL      = 'https://www.liqpay.ua/api/';

    public function name(): string
    {
        return 'liqpay';
    }

    /**
     * Формуємо redirect URL на LiqPay checkout.
     *
     * LiqPay не має server-side POST для redirect. Замість цього:
     * - data = base64(json_encode($params))
     * - signature = base64(sha1(private_key . data . private_key))
     * - Redirect: GET https://www.liqpay.ua/api/3/checkout?data=...&signature=...
     *
     * Або: auto-submit форма (так само як WFP form-mode).
     */
    public function createCheckout(CheckoutData $data): string
    {
        $params = [
            'public_key'        => config('payments.gateways.liqpay.public_key'),
            'version'           => '3',
            'action'            => 'pay',
            'amount'            => $data->amountUah(),        // float грн
            'currency'          => $data->currency,           // 'UAH'
            'description'       => $data->description,
            'order_id'          => $data->orderId,
            'result_url'        => $data->successUrl,
            'server_url'        => $data->webhookUrl,
            // 'paytypes' => 'card,liqpay,privat24', // за замовчуванням — усі
        ];

        $encodedData = $this->encodeData($params);
        $signature   = $this->buildSignature($encodedData);

        // GET redirect — LiqPay приймає дані через query string
        return self::CHECKOUT_URL . '?' . http_build_query([
            'data'      => $encodedData,
            'signature' => $signature,
        ]);
    }

    /**
     * Верифікація SHA1 та парсинг webhook від LiqPay.
     *
     * LiqPay надсилає POST із двома полями: `data` і `signature`.
     * data = base64(json_encode(результат транзакції))
     * signature = base64(sha1(private_key . data . private_key))
     */
    public function parseWebhook(Request $request): PaymentResult
    {
        $rawData   = $request->input('data', '');
        $signature = $request->input('signature', '');

        if (! $rawData || ! $signature) {
            throw new InvalidWebhookSignatureException(
                'LiqPay: missing data or signature in webhook body'
            );
        }

        // Верифікуємо підпис
        $expectedSignature = $this->buildSignature($rawData);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidWebhookSignatureException(
                "LiqPay: SHA1 signature mismatch"
            );
        }

        // Декодуємо дані
        $decoded = json_decode(base64_decode($rawData), true);
        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('LiqPay: cannot decode webhook data');
        }

        Log::channel('payments')->debug('LiqPay webhook received', [
            'status'   => $decoded['status']   ?? '?',
            'order_id' => $decoded['order_id'] ?? '?',
        ]);

        // LiqPay статуси:
        // success — оплачено; sandbox — тестова оплата (успішна в sandbox)
        // failure, error — помилка; reversed — повернення; wait_* — очікування
        $status = $decoded['status'] ?? '';
        $isPaid = in_array($status, ['success', 'sandbox'], true);

        $orderId = $decoded['order_id'] ?? '';
        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        // LiqPay повертає суму у грн (float), переводимо у копійки
        $amountKopecks = (int) round((float) ($decoded['amount'] ?? 0) * 100);

        return new PaymentResult(
            isPaid:          $isPaid,
            gatewayName:     $this->name(),
            externalEventId: $decoded['payment_id'] ?? uniqid('liqpay_', true),
            orderId:         $orderId,
            amountKopecks:   $amountKopecks,
            currency:        $decoded['currency'] ?? 'UAH',
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
            failureReason:   $isPaid ? null : ($decoded['err_description'] ?? "status={$status}"),
        );
    }

    /**
     * LiqPay не вимагає специфічної відповіді — порожній 200 прийнятний.
     */
    public function successResponse(): \Illuminate\Http\Response
    {
        return response('');
    }

    // =========================================================================
    // Допоміжні методи
    // =========================================================================

    private function encodeData(array $params): string
    {
        return base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * SHA1 підпис: base64(sha1(private_key + data + private_key))
     * Зверни увагу: це НЕ HMAC! Просто конкатенація.
     */
    private function buildSignature(string $encodedData): string
    {
        $privateKey = config('payments.gateways.liqpay.private_key');
        return base64_encode(sha1($privateKey . $encodedData . $privateKey, true));
    }
}
```

---

## 📂 КРОК 14.3. Маршрут — важлива деталь

LiqPay надсилає webhook як `application/x-www-form-urlencoded`, а не JSON. `$request->input()` (не `$request->json()`) у `parseWebhook` — це вже враховано. Але треба переконатись, що Laravel не парсить його як JSON.

Маршрут у `routes/web.php`:

```php
// Загальний маршрут вже є з модуля 11A:
// Route::post('/webhooks/payments/{gateway}', ...)

// Додатково: LiqPay іноді надсилає GET-запит (preview / перевірка URL)
// Краще явно заблокувати GET для webhook URL
```

У `WebhookController` вже є перевірка на невідомий `$gateway` → 404. Все гаразд.

---

## 📂 КРОК 14.4. Конфігурація

`config/payments.php` → секція `liqpay` вже є. Перевір:

```php
'liqpay' => [
    'public_key'  => env('LIQPAY_PUBLIC_KEY'),
    'private_key' => env('LIQPAY_PRIVATE_KEY'),
],
```

`.env.example`:

```ini
# LiqPay
LIQPAY_PUBLIC_KEY=sandbox_i00000000000001
LIQPAY_PRIVATE_KEY=sandbox_0000000000000000000000000000000000000001
```

**Sandbox-ключі.** LiqPay надає публічно-доступні sandbox ключі для тестування:
- Public key: `sandbox_i00000000000001`
- Private key: `sandbox_0000000000000000000000000000000000000001`

З ними платежі зі статусом `sandbox` (обробляємо як успішні).

---

## 🧪 КРОК 14.5. Перевірка

```bash
# 1. Перевір gateway у реєстрі
php artisan tinker --execute="
    \$r = app(\App\Http\Controllers\Payments\PaymentGatewayRegistry::class);
    dump(\$r->get('liqpay') instanceof \App\Payments\Gateways\LiqPayGateway);
"

# 2. Тест checkout URL
php artisan tinker
```
```php
config(['payments.default' => 'liqpay']);
$vacancy = \App\Models\Vacancy::first();
$svc = app(\App\Payments\CheckoutService::class);
$url = $svc->createVacancyExtensionCheckout($vacancy, 30);
// Очікуване: https://www.liqpay.ua/api/3/checkout?data=...&signature=...
echo $url;
```

```bash
# 3. Симуляція webhook
php artisan tinker
```
```php
use App\Payments\CheckoutService;
use App\Payments\Gateways\LiqPayGateway;

// Будуємо тестовий payload так само як LiqPay
$gateway = app(LiqPayGateway::class);
$orderId = CheckoutService::buildOrderId(1, 30);

$params = [
    'public_key' => env('LIQPAY_PUBLIC_KEY'),
    'private_key' => null, // не включається в data
    'version' => '3',
    'action' => 'pay',
    'payment_id' => 'test_' . uniqid(),
    'status' => 'sandbox',    // або 'success'
    'amount' => 200.00,
    'currency' => 'UAH',
    'order_id' => $orderId,
];

$encodedData = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
$privateKey = config('payments.gateways.liqpay.private_key');
$signature = base64_encode(sha1($privateKey . $encodedData . $privateKey, true));

// Надсилаємо через HTTP
\Illuminate\Support\Facades\Http::asForm()->post(route('webhooks.payments', ['gateway' => 'liqpay']), [
    'data'      => $encodedData,
    'signature' => $signature,
]);
```

---

## ⚠️ Критичні нюанси

### 1. SHA1, а не HMAC-SHA1
LiqPay використовує `sha1(key + data + key)` — це не стандартний HMAC. Це слабший підхід (вразливий до length extension attack теоретично), але так і є в їхньому API. **Не замінюй на HMAC** — підпис не збіжиться.

### 2. `sandbox` статус = успішна оплата в тестах
При sandbox ключах LiqPay повертає `status: "sandbox"`. Мій код обробляє його як `isPaid = true`. В продакшені з реальними ключами — тільки `status: "success"`.

Але будь уважний: при реальних ключах sandbox-транзакції все одно можуть мати статус `success`. Розрізняй за `public_key` (sandbox_ vs prod_).

### 3. Webhook у форматі `application/x-www-form-urlencoded`
LiqPay — одна з небагатьох систем, яка надсилає `POST` з полями форми (`data=...&signature=...`), а не JSON. `$request->json()` не спрацює. Правильно: `$request->input('data')`.

У `WebhookController` я викликаю `$gw->parseWebhook($request)` — кожен gateway сам вирішує, як читати запит. `LiqPayGateway::parseWebhook` використовує `$request->input()`. Це правильно.

### 4. GET-запит на webhook URL
LiqPay іноді робить GET перед реєстрацією URL (перевіряє доступність). Наш маршрут — `POST`. GET поверне 405 Method Not Allowed — це нормально, LiqPay прийме це.

### 5. `amount` у грн (float), не копійках
LiqPay повертає `amount: 200.00` у webhook. Я конвертую в копійки: `(int) round(200.00 * 100)` = 20000. Float множення може давати 19999 або 20001 — `round()` + `int` захищає.

### 6. `payment_id` — унікальний ID транзакції LiqPay
Це їхній внутрішній ідентифікатор (`payment_id`), не твій `order_id`. Ми зберігаємо його як `externalEventId` у `payment_processed_events` — це і є idempotency-ключ.

### 7. Повернення / Refund
LiqPay надсилає webhook зі статусом `reversed` при поверненні. Поточна реалізація: `isPaid = false`, webhook ігнорується. Якщо треба повернення — обробляти окремо.

### 8. Ключі не ротуються (обережно)
LiqPay private key — довготривалий. При компрометації — треба вручну звертатись у підтримку PrivatBank. Не коміть у git, навіть у тестових гілках.

---

## ✅ Очікуваний результат

1. `app/Payments/Gateways/LiqPayGateway.php` створено.
2. `config/payments.php` → `liqpay` секція.
3. Sandbox-ключі у `.env` для тестування.
4. Перевірка через tinker: checkout URL коректний, webhook декодується.

Звіт:
```
LiqPayGateway готовий.
SHA1 верифікація (private_key + data + private_key).
Webhook: application/x-www-form-urlencoded → $request->input().
Sandbox статус обробляється як успішна оплата.

Перейти до модуля 15 (тести провайдерів)? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не використовуй HMAC — підпис LiqPay не є HMAC.
- ❌ Не парсь webhook через `$request->json()` — формат `x-www-form-urlencoded`.
- ❌ Не ігноруй `sandbox` статус у тестах — це успішна оплата в sandbox.
- ❌ Не зберігай private_key у коді, коментарях або commits.
- ❌ Не передавай `private_key` у полі `data` — він лише для підпису.
