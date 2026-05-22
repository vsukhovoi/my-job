# МОДУЛЬ 13. WayForPay — інтеграція через офіційне API

## 🎯 Мета модуля
Реалізувати `WayForPayGateway`. WayForPay — один із найстаріших і найпоширеніших платіжних шлюзів в Україні, використовується тисячами сервісів. Специфіка: HMAC-MD5 підпис, форма `<form>` POST на стороні клієнта (не redirect), особливий формат відповіді `"accept"`.

**Передумова:** модуль 11A виконано.

> **Увага щодо форми.** WayForPay класично використовує `<form method="POST" action="https://secure.wayforpay.com/pay">` на стороні клієнта, а не redirect з бекенду. Це відрізняє його від MonoPay і LiqPay. У нас буде два підходи — обери один після розвідки.

---

## 🔍 КРОК 13.1. Розвідка

```bash
# Перевіримо наявні пакети
composer show wayforpay/php-sdk 2>/dev/null && echo "WFP SDK є" || echo "WFP SDK відсутній"

# Чи є якийсь власний код WayForPay?
find app -type f | xargs grep -l "wayforpay\|WayForPay" 2>/dev/null

# Переглянь .env.example
grep -i "wfp\|wayfor" .env.example
```

---

## 📐 Два підходи до checkout (обери один)

| Підхід | Як | Плюси | Мінуси |
|--------|-----|-------|--------|
| **A. Form POST** (класичний WFP) | Livewire-компонент рендерить `<form>` і автосабмітить | Підтримується всіма WFP-планами | Потрібен Livewire або Blade; JS `submit()` |
| **B. Hosted page** (через API) | POST на `/api/merchant/invoice/create`, отримуємо URL | Такий самий флоу як MonoPay | Доступний не у всіх тарифах WFP; перевір у Dashboard |

**Я реалізую обидва.** Обирай в `.env`: `WFP_CHECKOUT_MODE=form` або `WFP_CHECKOUT_MODE=hosted`.

---

## 📂 КРОК 13.2. `WayForPayGateway`

`app/Payments/Gateways/WayForPayGateway.php`:

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

class WayForPayGateway implements PaymentGateway
{
    private const FORM_URL = 'https://secure.wayforpay.com/pay';
    private const API_URL  = 'https://api.wayforpay.com/api';

    public function name(): string
    {
        return 'wayforpay';
    }

    /**
     * Повертає або:
     * - URL WFP Hosted page (якщо WFP_CHECKOUT_MODE=hosted)
     * - Спеціальний URL-заглушку, що рендерить форму (якщо WFP_CHECKOUT_MODE=form)
     *
     * У form-режимі реальний POST на WFP відбувається через Blade/Livewire компонент
     * (дивись КРОК 13.4).
     */
    public function createCheckout(CheckoutData $data): string
    {
        $mode = config('payments.gateways.wayforpay.checkout_mode', 'form');

        return match($mode) {
            'hosted' => $this->createHostedCheckout($data),
            'form'   => $this->createFormCheckoutUrl($data),
            default  => throw new \InvalidArgumentException("Unknown WFP checkout mode: {$mode}"),
        };
    }

    /**
     * Hosted mode: POST на WFP API, отримуємо redirect URL.
     */
    private function createHostedCheckout(CheckoutData $data): string
    {
        $params = $this->buildFormParams($data);

        $response = Http::post(self::API_URL, array_merge($params, [
            'transactionType' => 'CREATE_INVOICE',
        ]));

        if ($response->failed()) {
            throw new PaymentGatewayException('WayForPay: API error: ' . $response->body());
        }

        $invoiceUrl = $response->json('invoiceUrl');
        if (! $invoiceUrl) {
            throw new PaymentGatewayException(
                'WayForPay: empty invoiceUrl. Reason: ' . ($response->json('reason') ?? 'unknown')
            );
        }

        return $invoiceUrl;
    }

    /**
     * Form mode: повертаємо спеціальний route, який рендерить auto-submit форму.
     * Реальний перехід на WFP — через JS submit() у Blade/Livewire.
     */
    private function createFormCheckoutUrl(CheckoutData $data): string
    {
        // Зберігаємо параметри форми в cache з коротким TTL
        $cacheKey = 'wfp:form:' . $data->orderId;
        cache()->put($cacheKey, $this->buildFormParams($data), 600); // 10 хв

        return route('payments.wfp.form', ['orderId' => $data->orderId]);
    }

    /**
     * Верифікація HMAC-MD5 та парсинг webhook.
     *
     * WFP підписує рядок із ключових полів через HMAC-MD5.
     * Документація: https://wiki.wayforpay.com/view/852131
     */
    public function parseWebhook(Request $request): PaymentResult
    {
        $data = $request->json()->all();

        $this->verifyHmacSignature($data);

        // WFP статуси:
        // Approved, Expired, Declined, Refunded, Voided, Pending
        $transactionStatus = $data['transactionStatus'] ?? '';
        $isPaid = $transactionStatus === 'Approved';

        $orderId = $data['orderReference'] ?? '';
        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        // Сума у WFP — дробова (200.00), переводимо в копійки
        $amountKopecks = (int) round((float) ($data['amount'] ?? 0) * 100);

        return new PaymentResult(
            isPaid:          $isPaid,
            gatewayName:     $this->name(),
            externalEventId: $data['orderReference'] . ':' . ($data['recToken'] ?? uniqid()),
            orderId:         $orderId,
            amountKopecks:   $amountKopecks,
            currency:        $data['currency'] ?? 'UAH',
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
            failureReason:   $isPaid ? null : "status={$transactionStatus}",
        );
    }

    /**
     * WayForPay очікує відповідь "accept" або "deny" у специфічному форматі.
     * "accept" = ми успішно обробили.
     */
    public function successResponse(): \Illuminate\Http\Response
    {
        // Формат WFP: {"orderReference":"...", "status":"accept", "time":...,"signature":"..."}
        // Але спрощено WFP приймає і просто рядок "accept"
        $time = time();
        $orderReference = request()->json('orderReference', '');
        $signature = $this->buildResponseSignature($orderReference, 'accept', $time);

        return response()->json([
            'orderReference' => $orderReference,
            'status'         => 'accept',
            'time'           => $time,
            'signature'      => $signature,
        ]);
    }

    // =========================================================================
    // Побудова підпису
    // =========================================================================

    /**
     * Побудова підпису для запиту (HMAC-MD5).
     * Рядок для підпису: поля через ';' у визначеному порядку.
     *
     * Порядок полів для `purchase` (з документації WFP):
     * merchantAccount;merchantDomainName;orderReference;orderDate;amount;currency;
     * productName[0];productCount[0];productPrice[0]
     */
    private function buildSignatureString(CheckoutData $data): string
    {
        return implode(';', [
            config('payments.gateways.wayforpay.merchant_account'),
            config('payments.gateways.wayforpay.merchant_domain'),
            $data->orderId,
            time(),
            $data->amountUah(),            // WFP приймає float (грн), не копійки
            $data->currency,
            $data->description,            // productName[0]
            1,                             // productCount[0]
            $data->amountUah(),            // productPrice[0]
        ]);
    }

    private function buildFormParams(CheckoutData $data): array
    {
        $orderDate = time();
        $amount    = $data->amountUah();

        $signatureString = implode(';', [
            config('payments.gateways.wayforpay.merchant_account'),
            config('payments.gateways.wayforpay.merchant_domain'),
            $data->orderId,
            $orderDate,
            $amount,
            $data->currency,
            $data->description,
            1,
            $amount,
        ]);

        $signature = hash_hmac(
            'md5',
            $signatureString,
            config('payments.gateways.wayforpay.merchant_password'),
        );

        return [
            'merchantAccount'     => config('payments.gateways.wayforpay.merchant_account'),
            'merchantDomainName'  => config('payments.gateways.wayforpay.merchant_domain'),
            'merchantTransactionSecureType' => 'AUTO',
            'orderReference'      => $data->orderId,
            'orderDate'           => $orderDate,
            'amount'              => $amount,
            'currency'            => $data->currency,
            'productName'         => [$data->description],
            'productCount'        => [1],
            'productPrice'        => [$amount],
            'clientEmail'         => $data->customerEmail ?? '',
            'clientFirstName'     => $data->customerName  ?? '',
            'serviceUrl'          => $data->webhookUrl,
            'returnUrl'           => $data->successUrl,
            'signature'           => $signature,
        ];
    }

    /**
     * Верифікація підпису вхідного webhook.
     *
     * Рядок для перевірки підпису відповіді WFP:
     * merchantAccount;orderReference;amount;currency;authCode;cardPan;transactionStatus;reasonCode
     */
    private function verifyHmacSignature(array $data): void
    {
        $fields = [
            $data['merchantAccount']    ?? '',
            $data['orderReference']     ?? '',
            $data['amount']             ?? '',
            $data['currency']           ?? '',
            $data['authCode']           ?? '',
            $data['cardPan']            ?? '',
            $data['transactionStatus']  ?? '',
            $data['reasonCode']         ?? '',
        ];

        $signatureString = implode(';', $fields);
        $expected = hash_hmac(
            'md5',
            $signatureString,
            config('payments.gateways.wayforpay.merchant_password'),
        );

        $received = $data['merchantSignature'] ?? '';

        if (! hash_equals($expected, $received)) {
            throw new InvalidWebhookSignatureException(
                "WayForPay: HMAC mismatch. Expected={$expected}, got={$received}"
            );
        }
    }

    private function buildResponseSignature(string $orderReference, string $status, int $time): string
    {
        $string = implode(';', [$orderReference, $status, $time]);
        return hash_hmac('md5', $string, config('payments.gateways.wayforpay.merchant_password'));
    }
}
```

---

## 📂 КРОК 13.3. Маршрут і контролер форми (для form-режиму)

Якщо обрано `WFP_CHECKOUT_MODE=form`, потрібна сторінка, що авто-сабмітить форму.

`routes/web.php`:

```php
Route::get('/payments/wfp/form/{orderId}', [\App\Http\Controllers\Payments\WfpFormController::class, 'show'])
    ->name('payments.wfp.form')
    ->middleware('auth');
```

`app/Http/Controllers/Payments/WfpFormController.php`:

```php
<?php

namespace App\Http\Controllers\Payments;

use Illuminate\Http\Request;

class WfpFormController extends \App\Http\Controllers\Controller
{
    public function show(Request $request, string $orderId)
    {
        $params = cache()->pull('wfp:form:' . $orderId);

        if (! $params) {
            abort(410, 'Посилання на оплату застаріло. Спробуйте ще раз.');
        }

        return view('payments.wfp-form', [
            'actionUrl' => 'https://secure.wayforpay.com/pay',
            'params'    => $params,
        ]);
    }
}
```

`resources/views/payments/wfp-form.blade.php`:

```blade
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Перехід до оплати...</title>
</head>
<body>
    <p>Зачекайте, перенаправляємо до WayForPay...</p>

    <form id="wfp-form" method="POST" action="{{ $actionUrl }}" style="display:none">
        @foreach($params as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
    </form>

    <script>
        document.getElementById('wfp-form').submit();
    </script>
</body>
</html>
```

---

## 📂 КРОК 13.4. Конфігурація

`config/payments.php` → секція `wayforpay`:

```php
'wayforpay' => [
    'merchant_account'  => env('WFP_MERCHANT_ACCOUNT'),
    'merchant_password' => env('WFP_MERCHANT_PASSWORD'),
    'merchant_domain'   => env('WFP_MERCHANT_DOMAIN', config('app.url')),
    'checkout_mode'     => env('WFP_CHECKOUT_MODE', 'form'), // 'form' | 'hosted'
],
```

`.env.example`:

```ini
# WayForPay
WFP_MERCHANT_ACCOUNT=test_merch_n1
WFP_MERCHANT_PASSWORD=
WFP_MERCHANT_DOMAIN=
WFP_CHECKOUT_MODE=form
```

---

## 🧪 КРОК 13.5. Як отримати доступ до WayForPay

1. Реєстрація: https://wayforpay.com → «Стати партнером»
2. Dashboard → Мерчанти → Твій мерчант → Ключі
3. `merchantAccount` (логін) і `secretKey` (пароль підпису)
4. Тестовий режим: увімкни в Dashboard → Налаштування → Тестовий режим

**Тест-картки:**
| Номер | CVV | Термін | Результат |
|-------|-----|--------|-----------|
| 4111 1111 1111 1111 | 111 | 11/26 | Успішна оплата |
| 4005 5192 0000 0004 | 123 | 12/26 | Відмова |

---

## ⚠️ Критичні нюанси

### 1. HMAC-MD5, не SHA256
WayForPay використовує MD5. Так, MD5 вважається слабким для криптографії, але в контексті HMAC і секретного ключа це прийнятно. **Не замінюй на SHA256** — WFP перевіряє з боку свого сервера, і вони очікують MD5.

### 2. Сума у гривнях (float), а не копійках
На відміну від MonoPay і Stripe, WFP приймає суму як `200.00` (float), а не `20000` (int). `amountUah()` конвертує правильно, але не забудь: у БД і в `PaymentResult` ми зберігаємо копійки.

### 3. `productName[]` — масив у form POST
WFP підтримує кілька товарів. Наш випадок — завжди один. При form POST: `<input name="productName[]">`, при API — масив у JSON. Мій код обробляє обидва.

### 4. Порядок полів у підписі КРИТИЧНИЙ
Якщо переставиш поля у `buildSignatureString` — підпис буде невалідним. Перевіряй завжди з документацією WFP (є онлайн-калькулятор підпису на їхньому сайті).

### 5. Відповідь `"accept"` з підписом
WFP очікує не просто `"accept"`, а JSON із підписаним підтвердженням. Мій `successResponse()` формує правильний формат. Якщо повернеш просто `response('accept')` — WFP може ретраїти webhook.

### 6. `form` vs `hosted` — що обрати
- **form** — надійніший, підтримується всіма тарифами WFP. Клієнт бачить проміжну сторінку 0.5 сек.
- **hosted** — новіший API, не всі мерчанти мають доступ. Перевір у Dashboard.

### 7. `merchantDomainName` має збігатися з реальним доменом
WFP перевіряє домен. При тестуванні з localhost — може не спрацювати. Використовуй ngrok або dev-домен.

### 8. `recToken` у webhook — токен для recurring
WFP повертає `recToken` після першої оплати. Це токен для повторних списань (recurring payments). Ми його не використовуємо, але не ігноруй у логах — може знадобитись в майбутньому.

---

## ✅ Очікуваний результат

1. `app/Payments/Gateways/WayForPayGateway.php` створено.
2. `app/Http/Controllers/Payments/WfpFormController.php` і view (якщо form-режим).
3. `config/payments.php` → секція `wayforpay` + `checkout_mode`.
4. Маршрути оновлено.
5. Gateway реєструється через `PaymentGatewayRegistry`.

Звіт:
```
WayForPayGateway готовий.
Режим: form (авто-сабміт) / hosted (за config).
HMAC-MD5 верифікація webhook, відповідь у форматі accept+signature.
Сума: float (грн) при передачі, int (копійки) у БД.

Перейти до модуля 14 (LiqPay)? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не замінюй MD5 на SHA256 у підписі — WFP перевіряє MD5.
- ❌ Не передавай суму у копійках — тільки float грн.
- ❌ Не ігноруй порядок полів у підписі — він фіксований у документації WFP.
- ❌ Не відповідай просто `"accept"` — повертай повний JSON з підписом.
- ❌ Не зберігай `WFP_MERCHANT_PASSWORD` ніде, крім `.env`.
