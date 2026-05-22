# МОДУЛЬ 11A. PaymentGateway — абстрактний шар для всіх провайдерів

## 🎯 Мета модуля
Ввести єдиний інтерфейс `PaymentGateway`, який однаково описує MonoPay, WayForPay, LiqPay і вже наявний Stripe. Після цього модуля:
- Всі webhook-контролери провайдерів кидають одну й ту саму подію `VacancyExtended` — без змін у Nutgram (модуль 8) і в тестах.
- Роботодавець обирає провайдер у кабінеті (або сайт завжди пропонує один) — `CheckoutService` вирішує, яку реалізацію викликати.
- Додати п'ятий провайдер у майбутньому = створити один клас + зареєструвати.

**Передумова:** модулі 1–6 виконано (Stripe вже є як референс-реалізація).

> **Виконуй цей модуль ДО модулів 12 (MonoPay), 13 (WayForPay), 14 (LiqPay).**
> Модулі 12–14 залежать від інтерфейсів, визначених тут.

---

## 🔍 КРОК 11A.1. Розвідка наявного коду

```bash
# 1. Перевір наявну структуру платіжного коду
find app -type f | grep -iE "payment|checkout|gateway" | sort

# 2. Чи є services.php записи для наших провайдерів?
grep -E "mono|wayfor|liqpay|stripe" config/services.php

# 3. Переглянь наявний StripeWebhookController
cat app/Http/Controllers/StripeWebhookController.php | head -40

# 4. Чи є вже CheckoutService?
find app -name "CheckoutService.php"
```

Відзвітуй мені. Далі — без мого OK не пиши.

---

## 📐 КРОК 11A.2. Файлова структура, яку ми створюємо

```
app/
└── Payments/
    ├── Contracts/
    │   ├── PaymentGateway.php          ← інтерфейс
    │   └── WebhookVerifier.php         ← інтерфейс верифікації підпису
    ├── DTOs/
    │   ├── CheckoutData.php            ← що передаємо gateway при створенні checkout
    │   └── PaymentResult.php           ← що отримуємо з webhook
    ├── Gateways/
    │   ├── StripeGateway.php           ← обгортка навколо наявного Stripe-коду
    │   ├── MonoPayGateway.php          ← модуль 12
    │   ├── WayForPayGateway.php        ← модуль 13
    │   └── LiqPayGateway.php           ← модуль 14
    ├── Exceptions/
    │   ├── InvalidWebhookSignatureException.php
    │   ├── PaymentGatewayException.php
    │   └── DuplicatePaymentException.php
    └── PaymentServiceProvider.php      ← реєструє гейтвеї + config binding
```

---

## 📂 КРОК 11A.3. Контракти (інтерфейси)

### `app/Payments/Contracts/PaymentGateway.php`

```php
<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\DTOs\CheckoutData;
use App\Payments\DTOs\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Ім'я провайдера — для логів, бази і .env-ключів.
     * Має збігатись з ключем у config/payments.php.
     */
    public function name(): string;

    /**
     * Створити сесію оплати і повернути URL для редіректу.
     *
     * @throws \App\Payments\Exceptions\PaymentGatewayException
     */
    public function createCheckout(CheckoutData $data): string;

    /**
     * Перевірити підпис webhook-запиту та розпарсити результат.
     * Кидає InvalidWebhookSignatureException при невалідному підписі.
     *
     * @throws \App\Payments\Exceptions\InvalidWebhookSignatureException
     * @throws \App\Payments\Exceptions\PaymentGatewayException
     */
    public function parseWebhook(Request $request): PaymentResult;

    /**
     * HTTP-відповідь, яку очікує провайдер при успішній обробці.
     * Stripe: {"status":"ok"} 200
     * WayForPay: "accept" 200
     * LiqPay: порожньо 200
     */
    public function successResponse(): \Illuminate\Http\Response;
}
```

### `app/Payments/DTOs/CheckoutData.php`

```php
<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

use App\Models\Vacancy;

/**
 * Всі дані, необхідні будь-якому гейтвею для створення checkout.
 */
final readonly class CheckoutData
{
    public function __construct(
        public Vacancy $vacancy,
        public int $days,              // 15 / 30 / 90
        public int $amountKopecks,     // сума у копійках (UAH × 100)
        public string $currency,       // 'UAH'
        public string $orderId,        // ваш унікальний ідентифікатор замовлення
        public string $description,    // назва у квитанції клієнта
        public string $successUrl,
        public string $cancelUrl,
        public string $webhookUrl,
        public ?string $customerEmail = null,
        public ?string $customerName  = null,
    ) {}

    /**
     * Сума у гривнях (float) — для провайдерів, що не приймають копійки.
     */
    public function amountUah(): float
    {
        return $this->amountKopecks / 100;
    }

    /**
     * Сума у мінімальних одиницях (int) — для Stripe / MonoPay.
     */
    public function amountMinorUnits(): int
    {
        return $this->amountKopecks;
    }
}
```

### `app/Payments/DTOs/PaymentResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

/**
 * Нормалізований результат будь-якого webhook'у будь-якого провайдера.
 */
final readonly class PaymentResult
{
    public function __construct(
        public bool $isPaid,                // true = гроші отримані
        public string $gatewayName,         // 'stripe' | 'mono' | 'wayforpay' | 'liqpay'
        public string $externalEventId,     // унікальний ID події (для idempotency)
        public string $orderId,             // ваш orderId, переданий при checkout
        public int $amountKopecks,          // скільки реально надійшло (у копійках)
        public string $currency,            // 'UAH'
        public ?string $vacancyId,          // витягнуто з orderId або metadata
        public ?int $days,                  // кількість днів продовження (з orderId/metadata)
        public ?string $failureReason = null, // причина відмови (якщо isPaid=false)
    ) {}
}
```

### Винятки

```php
// app/Payments/Exceptions/InvalidWebhookSignatureException.php
<?php
namespace App\Payments\Exceptions;
class InvalidWebhookSignatureException extends \RuntimeException {}

// app/Payments/Exceptions/PaymentGatewayException.php
<?php
namespace App\Payments\Exceptions;
class PaymentGatewayException extends \RuntimeException {}

// app/Payments/Exceptions/DuplicatePaymentException.php
<?php
namespace App\Payments\Exceptions;
class DuplicatePaymentException extends \RuntimeException {
    public function __construct(public readonly string $externalEventId) {
        parent::__construct("Event {$externalEventId} already processed.");
    }
}
```

---

## 📂 КРОК 11A.4. Спільний WebhookController

Один контролер для всіх провайдерів. Провайдер розрізняється за сегментом URL.

`app/Http/Controllers/Payments/WebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Events\VacancyExtended;
use App\Models\Vacancy;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\DuplicatePaymentException;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
    ) {}

    /**
     * POST /webhooks/payments/{gateway}
     * gateway: stripe | mono | wayforpay | liqpay
     */
    public function handle(Request $request, string $gateway): \Illuminate\Http\Response
    {
        $gw = $this->registry->get($gateway);

        if (! $gw) {
            Log::channel('payments')->warning("Unknown payment gateway: {$gateway}", [
                'ip' => $request->ip(),
            ]);
            return response('', 404);
        }

        // 1. Верифікація підпису (специфічна для кожного гейтвею)
        try {
            $result = $gw->parseWebhook($request);
        } catch (InvalidWebhookSignatureException $e) {
            Log::channel('payments')->warning("Invalid signature [{$gateway}]", [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response('', 400);
        } catch (\Throwable $e) {
            Log::channel('payments')->error("Webhook parse failed [{$gateway}]", [
                'error' => $e->getMessage(),
            ]);
            report($e);
            // 200 навіть при parse-помилці — запобігаємо retry-storm
            return $gw->successResponse();
        }

        // 2. Пропускаємо неоплачені (наприклад, pending або canceled)
        if (! $result->isPaid) {
            Log::channel('payments')->info("Not paid [{$gateway}]", [
                'event_id' => $result->externalEventId,
                'reason'   => $result->failureReason,
            ]);
            return $gw->successResponse();
        }

        // 3. Idempotency
        try {
            $this->checkIdempotency($result->externalEventId, $gateway);
        } catch (DuplicatePaymentException) {
            Log::channel('payments')->info("Duplicate event ignored [{$gateway}]", [
                'event_id' => $result->externalEventId,
            ]);
            return $gw->successResponse();
        }

        // 4. Бізнес-логіка: продовження вакансії
        try {
            $this->processExtension($result, $gateway);
        } catch (\Throwable $e) {
            Log::channel('payments')->error("Extension failed [{$gateway}]", [
                'event_id'   => $result->externalEventId,
                'vacancy_id' => $result->vacancyId,
                'error'      => $e->getMessage(),
            ]);
            report($e);
            // 200 — щоб провайдер не ретраїв нескінченно
            return $gw->successResponse();
        }

        // 5. Позначаємо як оброблену — тільки після успіху
        $this->markProcessed($result->externalEventId, $gateway, $result->orderId);

        return $gw->successResponse();
    }

    private function checkIdempotency(string $eventId, string $gateway): void
    {
        $exists = DB::table('payment_processed_events')
            ->where('event_id', $eventId)
            ->where('gateway', $gateway)
            ->exists();

        if ($exists) {
            throw new DuplicatePaymentException($eventId);
        }
    }

    private function processExtension(
        \App\Payments\DTOs\PaymentResult $result,
        string $gateway,
    ): void {
        if (! $result->vacancyId || ! $result->days) {
            throw new \UnexpectedValueException(
                "Cannot extract vacancy_id or days from orderId={$result->orderId}"
            );
        }

        DB::transaction(function () use ($result, $gateway) {
            $vacancy = Vacancy::lockForUpdate()->find((int) $result->vacancyId);

            if (! $vacancy) {
                throw new \DomainException("Vacancy {$result->vacancyId} not found");
            }

            $vacancy->extend($result->days);

            VacancyExtended::dispatch(
                vacancy:        $vacancy,
                days:           $result->days,
                amountCents:    $result->amountKopecks,
                currency:       $result->currency,
                stripeEventId:  $result->externalEventId, // поле перейменуємо в рефакторингу
            );

            Log::channel('payments')->info("Vacancy extended [{$gateway}]", [
                'vacancy_id' => $vacancy->id,
                'days'       => $result->days,
                'event_id'   => $result->externalEventId,
            ]);
        });
    }

    private function markProcessed(string $eventId, string $gateway, string $orderId): void
    {
        DB::table('payment_processed_events')->insert([
            'event_id'     => $eventId,
            'gateway'      => $gateway,
            'order_id'     => $orderId,
            'processed_at' => now(),
        ]);
    }
}
```

### `app/Http/Controllers/Payments/PaymentGatewayRegistry.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Payments\Contracts\PaymentGateway;

class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->name()] = $gateway;
    }

    public function get(string $name): ?PaymentGateway
    {
        return $this->gateways[$name] ?? null;
    }
}
```

---

## 📂 КРОК 11A.5. Міграція `payment_processed_events`

Замінює `stripe_processed_events` (або існує поряд, якщо Stripe вже в прод):

```bash
php artisan make:migration create_payment_processed_events_table
```

```php
Schema::create('payment_processed_events', function (Blueprint $table) {
    $table->string('event_id', 255);
    $table->string('gateway', 32);        // stripe | mono | wayforpay | liqpay
    $table->string('order_id', 255);      // ваш orderId
    $table->timestamp('processed_at')->useCurrent();

    $table->primary(['event_id', 'gateway']);   // разом — унікальна пара
    $table->index('processed_at');              // для очищення старих записів
    $table->index('order_id');                  // для пошуку по orderId
});
```

> **Якщо `stripe_processed_events` вже в прод** — не видаляй її. Замість цього:
> 1. Мігруй дані: `INSERT INTO payment_processed_events SELECT event_id, 'stripe', event_id, processed_at FROM stripe_processed_events`
> 2. Залиш стару таблицю ще на 2 тижні, потім дропни.
> Стару таблицю ми більше не пишемо — `StripeGateway` буде писати в нову.

---

## 📂 КРОК 11A.6. Маршрути

`routes/web.php`:

```php
use App\Http\Controllers\Payments\WebhookController;

Route::post('/webhooks/payments/{gateway}', [WebhookController::class, 'handle'])
    ->name('webhooks.payments');
```

`bootstrap/app.php` (Laravel 11+) або `VerifyCsrfToken::$except`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhooks/payments/*',
    ]);
})
```

---

## 📂 КРОК 11A.7. `CheckoutService` — вибір провайдера

`app/Payments/CheckoutService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Vacancy;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;

class CheckoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway, // bound в config або через tag
    ) {}

    /**
     * Повертає URL для редіректу на сторінку оплати.
     */
    public function createVacancyExtensionCheckout(Vacancy $vacancy, int $days): string
    {
        $prices = config('payments.prices');
        $amountKopecks = $prices[$days] ?? throw new \InvalidArgumentException("Unknown plan: {$days} days");

        $orderId = $this->buildOrderId($vacancy->id, $days);

        $data = new CheckoutData(
            vacancy:       $vacancy,
            days:          $days,
            amountKopecks: $amountKopecks,
            currency:      'UAH',
            orderId:       $orderId,
            description:   "Публікація вакансії «{$vacancy->title}» на {$days} днів",
            successUrl:    route('vacancies.show', $vacancy),
            cancelUrl:     route('vacancies.show', $vacancy),
            webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
            customerEmail: $vacancy->employer?->user?->email,
            customerName:  $vacancy->employer?->name,
        );

        return $this->gateway->createCheckout($data);
    }

    /**
     * OrderId кодує vacancy_id і days, щоб webhook міг їх розпарсити.
     * Формат: vac_{vacancyId}_{days}_{randomSuffix}
     * Приклад: vac_42_30_a3f7k2
     *
     * Чому не просто metadata: не всі провайдери підтримують metadata в webhook.
     */
    public static function buildOrderId(int $vacancyId, int $days): string
    {
        return sprintf('vac_%d_%d_%s', $vacancyId, $days, substr(uniqid(), -6));
    }

    /**
     * Парсить orderId → [vacancyId, days] або [null, null] якщо формат невідомий.
     */
    public static function parseOrderId(string $orderId): array
    {
        if (preg_match('/^vac_(\d+)_(\d+)_/', $orderId, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        return [null, null];
    }
}
```

---

## 📂 КРОК 11A.8. `config/payments.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Активний провайдер за замовчуванням
    |--------------------------------------------------------------------------
    | Ключі: stripe | mono | wayforpay | liqpay
    */
    'default' => env('PAYMENT_GATEWAY', 'mono'),

    /*
    |--------------------------------------------------------------------------
    | Ціни (у копійках = грн × 100)
    |--------------------------------------------------------------------------
    */
    'prices' => [
        15  => (int) env('PRICE_15_DAYS_KOPECKS', 10000),   // 100 грн
        30  => (int) env('PRICE_30_DAYS_KOPECKS', 20000),   // 200 грн
        90  => (int) env('PRICE_90_DAYS_KOPECKS', 50000),   // 500 грн
    ],

    /*
    |--------------------------------------------------------------------------
    | Налаштування провайдерів
    |--------------------------------------------------------------------------
    */
    'gateways' => [

        'stripe' => [
            'key'            => env('STRIPE_KEY'),
            'secret'         => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'mono' => [
            'token'          => env('MONO_TOKEN'),
            'webhook_secret' => env('MONO_WEBHOOK_SECRET'),  // X-Sign-Base64 верифікація
            'public_key'     => env('MONO_PUBLIC_KEY'),      // Ed25519 публічний ключ
        ],

        'wayforpay' => [
            'merchant_account'  => env('WFP_MERCHANT_ACCOUNT'),
            'merchant_password' => env('WFP_MERCHANT_PASSWORD'),
            'merchant_domain'   => env('WFP_MERCHANT_DOMAIN', config('app.url')),
        ],

        'liqpay' => [
            'public_key'  => env('LIQPAY_PUBLIC_KEY'),
            'private_key' => env('LIQPAY_PRIVATE_KEY'),
        ],
    ],
];
```

`.env.example` — додай:

```ini
PAYMENT_GATEWAY=mono

PRICE_15_DAYS_KOPECKS=10000
PRICE_30_DAYS_KOPECKS=20000
PRICE_90_DAYS_KOPECKS=50000

# MonoPay
MONO_TOKEN=
MONO_WEBHOOK_SECRET=
MONO_PUBLIC_KEY=

# WayForPay
WFP_MERCHANT_ACCOUNT=
WFP_MERCHANT_PASSWORD=
WFP_MERCHANT_DOMAIN=

# LiqPay
LIQPAY_PUBLIC_KEY=
LIQPAY_PRIVATE_KEY=
```

---

## 📂 КРОК 11A.9. `PaymentServiceProvider`

`app/Payments/PaymentServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payments;

use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\LiqPayGateway;
use App\Payments\Gateways\MonoPayGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\Gateways\WayForPayGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payments.php', 'payments');

        // Реєструємо реєстр гейтвеїв як singleton
        $this->app->singleton(PaymentGatewayRegistry::class, function () {
            $registry = new PaymentGatewayRegistry();
            $registry->register($this->app->make(StripeGateway::class));
            $registry->register($this->app->make(MonoPayGateway::class));
            $registry->register($this->app->make(WayForPayGateway::class));
            $registry->register($this->app->make(LiqPayGateway::class));
            return $registry;
        });

        // Binding для CheckoutService — бере активний гейтвей з config
        $this->app->bind(PaymentGateway::class, function () {
            $name = config('payments.default', 'mono');
            return $this->app->make(PaymentGatewayRegistry::class)->get($name)
                ?? throw new \RuntimeException("Payment gateway '{$name}' not registered.");
        });

        $this->app->bind(CheckoutService::class, function () {
            return new CheckoutService($this->app->make(PaymentGateway::class));
        });
    }

    public function boot(): void
    {
        // Нічого — вся конфігурація в register
    }
}
```

Зареєструй у `bootstrap/providers.php` (Laravel 11+):

```php
return [
    // ...
    App\Payments\PaymentServiceProvider::class,
];
```

---

## 📂 КРОК 11A.10. `StripeGateway` — рефакторинг наявного коду

Тепер обертаємо наявний `StripeWebhookController` в інтерфейс. Сам контролер залишається, але `parseWebhook` виносимо в Gateway.

`app/Payments/Gateways/StripeGateway.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\CheckoutService;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(CheckoutData $data): string
    {
        Stripe::setApiKey(config('payments.gateways.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => strtolower($data->currency),
                    'unit_amount'  => $data->amountMinorUnits(),
                    'product_data' => ['name' => $data->description],
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => $data->successUrl,
            'cancel_url'  => $data->cancelUrl,
            'client_reference_id' => $data->orderId,
            'metadata' => [
                'type'       => 'vacancy_extension',
                'vacancy_id' => (string) $data->vacancy->id,
                'days'       => (string) $data->days,
                'order_id'   => $data->orderId,
            ],
        ]);

        return $session->url;
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        try {
            $event = Webhook::constructEvent(
                payload:   $request->getContent(),
                sigHeader: $request->header('Stripe-Signature', ''),
                secret:    config('payments.gateways.stripe.webhook_secret'),
                tolerance: 300,
            );
        } catch (SignatureVerificationException $e) {
            throw new InvalidWebhookSignatureException($e->getMessage());
        }

        if ($event->type !== 'checkout.session.completed') {
            // Повертаємо "не оплачено" — WebhookController проігнорує
            return new PaymentResult(
                isPaid:          false,
                gatewayName:     $this->name(),
                externalEventId: $event->id,
                orderId:         '',
                amountKopecks:   0,
                currency:        'UAH',
                vacancyId:       null,
                days:            null,
                failureReason:   "Unhandled event type: {$event->type}",
            );
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return new PaymentResult(
                isPaid:          false,
                gatewayName:     $this->name(),
                externalEventId: $event->id,
                orderId:         $session->client_reference_id ?? '',
                amountKopecks:   0,
                currency:        'UAH',
                vacancyId:       null,
                days:            null,
                failureReason:   "payment_status={$session->payment_status}",
            );
        }

        $orderId = $session->client_reference_id ?? ($session->metadata->order_id ?? '');
        [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

        return new PaymentResult(
            isPaid:          true,
            gatewayName:     $this->name(),
            externalEventId: $event->id,
            orderId:         $orderId,
            amountKopecks:   (int) $session->amount_total,
            currency:        strtoupper((string) $session->currency),
            vacancyId:       $vacancyId ? (string) $vacancyId : null,
            days:            $days,
        );
    }

    public function successResponse(): \Illuminate\Http\Response
    {
        return response()->json(['status' => 'ok']);
    }
}
```

---

## ⚠️ Критичні нюанси

### 1. Один маршрут замість чотирьох
`/webhooks/payments/{gateway}` автоматично підтримує всі зареєстровані провайдери. Якщо нові провайдери отримують запити на `/webhooks/payments/newpay` — вони автоматично обробляться, якщо `NewPayGateway` зареєстровано в `PaymentServiceProvider`.

### 2. Ціни в `config/payments.php` через `.env`
Ціна може змінитись без деплою — через `APP_PRICE_30=25000`. Але зберігай копійки: `20000` = 200 грн. Float у грошах — баг.

### 3. `orderId` кодує vacancy_id і days
Більшість українських провайдерів не мають аналогу Stripe `metadata`. `vac_42_30_a3f7k2` — повна інформація в самому ID, без додаткових полів.

### 4. Залишковий `StripeWebhookController`
Якщо `StripeWebhookController` вже в прод і не можна міняти URL вебхука в Stripe Dashboard прямо зараз — залиши його паралельно. `StripeGateway` новий код, старий контролер — старий URL. Поступово переходь.

### 5. `VacancyExtended` event незмінний
`stripeEventId` поле в events DTO — технічно це `externalEventId`, але переіменовувати поточну конструктуру не треба. Можна додати `@deprecated stripeEventId` коментар і залишити для сумісності.

---

## ✅ Очікуваний результат модуля

1. `app/Payments/Contracts/PaymentGateway.php` та `DTOs/` — створено.
2. `app/Payments/Exceptions/` — три класи винятків.
3. `app/Http/Controllers/Payments/WebhookController.php` — спільний контролер.
4. `app/Http/Controllers/Payments/PaymentGatewayRegistry.php` — реєстр.
5. `app/Payments/CheckoutService.php` — з `buildOrderId` / `parseOrderId`.
6. `config/payments.php` та `.env.example` — оновлено.
7. `app/Payments/PaymentServiceProvider.php` — зареєстровано.
8. `app/Payments/Gateways/StripeGateway.php` — Stripe адаптований до інтерфейсу.
9. Міграція `payment_processed_events` — виконана.
10. Маршрут `/webhooks/payments/{gateway}` зареєстровано, CSRF виключено.

Звіт:
```
Абстракція PaymentGateway готова.
Маршрут: POST /webhooks/payments/{gateway}
Провайдери в реєстрі: stripe (готовий), mono/wayforpay/liqpay (заглушки).
Активний за замовчуванням: mono (PAYMENT_GATEWAY=mono)

Перейти до модуля 12 (MonoPay)? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не видаляй `StripeWebhookController` якщо він в прод — тільки поступова міграція.
- ❌ Не зберігай ціни як float (`200.00`) — тільки integer копійок (`20000`).
- ❌ Не хардкодь `vacancy_id` в URL checkout (`.../pay/42`) — його можна перехопити. Кодуй в `orderId`.
- ❌ Не виконуй `php artisan migrate` без мого підтвердження.
- ❌ Не створюй Blade-форму checkout у цьому модулі — це окремий UI-модуль.
