# МОДУЛЬ 6 (розгорнутий). Stripe Webhook — оплата = продовження вакансії

## 🎯 Мета модуля
Інтеграція платежів Stripe з життєвим циклом вакансії. Коли клієнт оплачує продовження публікації — webhook перевіряє підпис, ідемпотентно обробляє подію, продовжує термін на куплену кількість днів і кидає Laravel-подію `VacancyExtended`, яку слухає модуль 8 (Nutgram).

**Передумова:** модулі 1–5 виконано. У `composer.json` повинен бути пакет `stripe/stripe-php` (`^15.0` або новіший).

> **Важлива примітка щодо порядку виконання.**
> Якщо в проєкті планується підтримка MonoPay / WayForPay / LiqPay (модулі 11A–14) — **пропусти цей модуль** і починай з **11A** (PaymentGateway абстракція). Модуль 11A містить рефакторинговану версію Stripe-інтеграції (`StripeGateway`) яка вбудована в спільний `WebhookController`. Виконувати модуль 6 окремо в цьому разі — означає подвійну роботу.
>
> Виконуй модуль 6 **лише якщо Stripe — єдиний провайдер** і мультигейтвей не планується.

> **Це найкритичніший модуль за наслідками.** Помилка тут = втрачені гроші, дублікати оплат, або вакансії, не продовжені після списання коштів. **Усі CRITICAL-нюанси нижче — обов'язкові.**

---

## 🔍 КРОК 6.1. Розвідка інтеграції

Перш ніж писати код — виведи мені:

```bash
# 1. Версія пакету
composer show stripe/stripe-php | grep versions

# 2. Чи є вже контролер вебхуків?
find app -type f -name "*StripeWebhook*"
find app -type f -name "*Webhook*Controller*"

# 3. Які роути зареєстровані?
php artisan route:list | grep -i stripe

# 4. Які env-змінні налаштовані (НЕ показуй значення, тільки список ключів!)
grep -E '^STRIPE_' .env.example
grep -E '^STRIPE_' .env | awk -F= '{print $1}'

# 5. Чи існує таблиця payments?
php artisan tinker --execute="dump(Schema::hasTable('payments'));"

# 6. Чи існує таблиця idempotency (оновлено для мультигейтвею)?
# Якщо виконано модуль 11A — шукай payment_processed_events (спільна для всіх провайдерів)
# Якщо 11A ще не виконано — шукай stripe_processed_events (Stripe-specific)
php artisan tinker --execute="dump(Schema::hasTable('payment_processed_events'), Schema::hasTable('stripe_processed_events'));"
```

**Не пиши код, поки я не побачу цей звіт і не дам OK на стратегію.**

---

## 🗺️ КРОК 6.2. Стратегія залежно від наявного коду

| Ситуація | Дія |
|----------|-----|
| Контролера вебхуків НЕМАЄ | Створюй `StripeWebhookController` з нуля (нижче) |
| Контролер є, але без `checkout.session.completed` | Додай **тільки** новий метод-обробник, не зачіпай інших |
| Контролер є, з `checkout.session.completed` уже обробляється для іншого продукту | **СТОП.** Обговори зі мною — як розрізняти типи checkout (по `metadata.type`?) |
| Таблиці `payments` немає | Логи в окремий канал `payments` + TODO. Не створюй таблицю в цьому модулі |
| Таблиці `stripe_processed_events` немає і **11A не виконано** | Створюй `stripe_processed_events` (нижче) — без неї немає захисту від дублікатів |
| Таблиця `payment_processed_events` вже є (11A виконано) | **Пропусти секцію 6.3** — `StripeGateway` з 11A пише в неї |

---

## 📂 КРОК 6.3. Міграція для idempotency

> ⚠️ **Якщо модуль 11A вже виконано** — `payment_processed_events` вже є, цей крок пропускай.
> Якщо 11A ще **не** виконано — створюй:

```bash
php artisan make:migration create_stripe_processed_events_table
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_processed_events', function (Blueprint $table) {
            $table->string('event_id')->primary();         // evt_1NXxxxx — Stripe event id
            $table->string('event_type', 64);              // checkout.session.completed
            $table->timestamp('processed_at')->useCurrent();
            $table->index('processed_at');                 // для періодичного очищення старих
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_processed_events');
    }
};
```

**Альтернатива через Redis** (якщо не хочеш окрему таблицю): використовуй `Cache::add("stripe:event:{$eventId}", true, now()->addDays(7))`. Повертає `false`, якщо ключ уже існує — це і є idempotency.

**Рекомендую таблицю**, бо:
- Перегляд історії через адмінку
- Не зникає при flush Redis
- Можна додати додаткові поля (`vacancy_id`, `amount`) для аудиту

---

## 📂 КРОК 6.4. Подія `VacancyExtended`

```bash
php artisan make:event VacancyExtended
```

`app/Events/VacancyExtended.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Vacancy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VacancyExtended
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vacancy $vacancy,
        public readonly int $days,
        public readonly int $amountCents,         // у копійках/центах для логу
        public readonly string $currency,         // ISO 4217: UAH / USD
        public readonly string $stripeEventId,    // для трасування
    ) {}
}
```

---

## 📂 КРОК 6.5. Контролер вебхуків

Якщо контролера ще немає — створи `app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use App\Models\Vacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Endpoint, на який Stripe постить події.
     * Маршрут: POST /webhooks/stripe (без CSRF — додай у VerifyCsrfToken::$except)
     */
    public function handle(Request $request): JsonResponse
    {
        // CRITICAL #1: верифікація підпису
        try {
            $event = Webhook::constructEvent(
                payload: $request->getContent(),
                sigHeader: $request->header('Stripe-Signature', ''),
                secret: config('services.stripe.webhook_secret'),
                tolerance: 300,  // 5 хвилин — стандарт Stripe
            );
        } catch (SignatureVerificationException $e) {
            Log::channel('payments')->warning('Stripe webhook: invalid signature', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::channel('payments')->warning('Stripe webhook: invalid payload', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // CRITICAL #2: idempotency — не обробляємо ту саму подію двічі
        $alreadyProcessed = DB::table('stripe_processed_events')
            ->where('event_id', $event->id)
            ->exists();

        if ($alreadyProcessed) {
            Log::channel('payments')->info('Stripe webhook: duplicate event ignored', [
                'event_id'   => $event->id,
                'event_type' => $event->type,
            ]);
            return response()->json(['status' => 'duplicate'], 200);
        }

        // Роутинг подій
        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event),
                // 'invoice.payment_failed'   => $this->handlePaymentFailed($event),  // майбутнє
                default => Log::channel('payments')->info('Stripe webhook: unhandled event type', [
                    'event_id'   => $event->id,
                    'event_type' => $event->type,
                ]),
            };

            // CRITICAL #3: позначаємо як оброблене ТІЛЬКИ після успіху бізнес-логіки
            DB::table('stripe_processed_events')->insert([
                'event_id'     => $event->id,
                'event_type'   => $event->type,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // CRITICAL #4: НЕ повертаємо 500 — Stripe буде retry-їти й ми отримаємо ту саму помилку.
            // Логуємо і повертаємо 200, щоб Stripe не дублював.
            // Прод-моніторинг (Sentry) поінформує нас.
            Log::channel('payments')->error('Stripe webhook: handler failed', [
                'event_id'   => $event->id,
                'event_type' => $event->type,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            report($e);

            // ВИНЯТОК: для критичних помилок верифікації даних (наприклад, vacancy_id не знайдено)
            // — теж 200, бо retry не виправить ситуацію.
            return response()->json(['status' => 'error_logged'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Обробка події `checkout.session.completed` для продовження вакансії.
     */
    private function handleCheckoutCompleted(Event $event): void
    {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        $logContext = [
            'event_id'   => $event->id,
            'session_id' => $session->id,
            'metadata'   => $session->metadata?->toArray() ?? [],
        ];

        // CRITICAL #5: розрізняємо тип checkout по metadata.type
        $type = $session->metadata->type ?? null;
        if ($type !== 'vacancy_extension') {
            Log::channel('payments')->info('Stripe webhook: not a vacancy extension, skipping', $logContext);
            return;
        }

        // Валідація payment_status
        if ($session->payment_status !== 'paid') {
            Log::channel('payments')->warning('Stripe webhook: session not paid yet', array_merge(
                $logContext,
                ['payment_status' => $session->payment_status],
            ));
            return;
        }

        // Витягуємо метадані
        $vacancyId = (int) ($session->metadata->vacancy_id ?? 0);
        $days = (int) ($session->metadata->days ?? 0);

        if ($vacancyId <= 0 || ! in_array($days, [15, 30, 90], true)) {
            // Логуємо як критичну помилку — це означає, що checkout створено з некоректним metadata
            Log::channel('payments')->error('Stripe webhook: invalid metadata', array_merge(
                $logContext,
                ['vacancy_id' => $vacancyId, 'days' => $days],
            ));
            return;
        }

        // CRITICAL #6: транзакційне продовження
        DB::transaction(function () use ($vacancyId, $days, $session, $event, $logContext) {
            // Lock для запобігання race condition зі scheduler-ом (модуль 4)
            $vacancy = Vacancy::query()->lockForUpdate()->find($vacancyId);

            if (! $vacancy) {
                Log::channel('payments')->error('Stripe webhook: vacancy not found', array_merge(
                    $logContext,
                    ['vacancy_id' => $vacancyId],
                ));
                return;
            }

            // НЕ можна продовжити архівовану — це бізнес-обмеження
            if ($vacancy->status === VacancyStatus::Archived) {
                Log::channel('payments')->warning('Stripe webhook: cannot extend archived vacancy', array_merge(
                    $logContext,
                    ['vacancy_id' => $vacancyId],
                ));
                // ⚠️ Гроші вже списані. Тут треба запустити refund через Stripe API
                // або хоча б створити запис у tasks для ручної обробки.
                $this->createRefundTask($session, $vacancy, 'vacancy_archived');
                return;
            }

            // Власне продовження
            $vacancy->extend($days);

            // CRITICAL #7: подія для слухачів (Nutgram, аналітика, листи)
            VacancyExtended::dispatch(
                vacancy: $vacancy,
                days: $days,
                amountCents: (int) $session->amount_total,
                currency: strtoupper((string) $session->currency),
                stripeEventId: $event->id,
            );

            Log::channel('payments')->info('Stripe webhook: vacancy extended', array_merge(
                $logContext,
                [
                    'vacancy_id'   => $vacancy->id,
                    'days'         => $days,
                    'new_expires_at' => $vacancy->expires_at?->toIso8601String(),
                ],
            ));
        });
    }

    /**
     * Заглушка для майбутньої таблиці refund_tasks.
     * Поки що — лог + Sentry alert.
     */
    private function createRefundTask(\Stripe\Checkout\Session $session, Vacancy $vacancy, string $reason): void
    {
        Log::channel('payments')->critical('Stripe webhook: REFUND REQUIRED', [
            'session_id'    => $session->id,
            'payment_intent' => $session->payment_intent,
            'vacancy_id'    => $vacancy->id,
            'amount'        => $session->amount_total,
            'currency'      => $session->currency,
            'reason'        => $reason,
        ]);

        // TODO: коли з'явиться таблиця refund_tasks — створювати запис тут.
        // Поки що Sentry-алерт через level=critical і ручне реагування.
    }
}
```

---

## 🔧 КРОК 6.6. Реєстрація маршруту

Файл `routes/web.php` (або `routes/api.php` — обрати один!):

```php
use App\Http\Controllers\StripeWebhookController;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
```

**КРИТИЧНО:** додай URI до `VerifyCsrfToken::$except` (або `bootstrap/app.php` для Laravel 11+):

```php
// app/Http/Middleware/VerifyCsrfToken.php — Laravel 10
protected $except = [
    'webhooks/stripe',
];
```

```php
// bootstrap/app.php — Laravel 11+
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhooks/stripe',
    ]);
})
```

---

## 🔐 КРОК 6.7. Конфігурація `services.php` та `.env`

Файл `config/services.php`:

```php
'stripe' => [
    'key'            => env('STRIPE_KEY'),
    'secret'         => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

Файл `.env.example`:

```ini
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

**УВАГА:** `STRIPE_WEBHOOK_SECRET` — це окремий секрет від `STRIPE_SECRET`. Береться з Dashboard → Developers → Webhooks → твій endpoint → Signing secret.

---

## 🛒 КРОК 6.8. Створення Checkout Session (контекст для розуміння)

Ось як ВИКОРИСТОВУЄТЬСЯ цей webhook (НЕ пиши цей код у модулі 6 — це модуль для оплати, який буде окремо):

```php
// Десь у VacancyController@createCheckout — поки референс
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency'     => 'uah',
            'unit_amount'  => 20000,  // 200 грн у копійках
            'product_data' => ['name' => "Продовження вакансії на {$days} днів"],
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => route('vacancies.show', $vacancy),
    'cancel_url'  => route('vacancies.show', $vacancy),
    'metadata' => [
        'type'       => 'vacancy_extension',  // КРИТИЧНО — інакше webhook пропустить
        'vacancy_id' => (string) $vacancy->id,
        'days'       => (string) $days,        // лише '15', '30', '90'
    ],
]);
```

Метадані Stripe — завжди рядки! `(string)` обов'язково.

---

## 🧪 КРОК 6.9. Локальне тестування зі Stripe CLI

```bash
# 1. Установи Stripe CLI (один раз)
# https://stripe.com/docs/stripe-cli

# 2. Залогінься
stripe login

# 3. Перенаправ події на локальний сервер
stripe listen --forward-to localhost:8000/webhooks/stripe
# Виведе webhook_signing_secret — скопіюй у .env як STRIPE_WEBHOOK_SECRET

# 4. У ОКРЕМОМУ терміналі — тригер тестової події
stripe trigger checkout.session.completed \
  --add checkout_session:metadata.type=vacancy_extension \
  --add checkout_session:metadata.vacancy_id=1 \
  --add checkout_session:metadata.days=30
```

**Очікуваний результат:**
- Лог `storage/logs/payments-YYYY-MM-DD.log` містить запис `Stripe webhook: vacancy extended`.
- У БД: вакансія #1 має `expires_at` на 30 днів пізніше.
- У БД: таблиця `stripe_processed_events` (або `payment_processed_events` якщо 11A виконано) має новий запис.

---

## ⚠️ Критичні нюанси (підсумок)

### CRITICAL #1: Верифікація підпису ДО будь-якого парсингу
Якщо обходити цей крок — будь-хто може POST-ити фейкові події і безкоштовно продовжувати вакансії. **Ніколи** не починай обробку до `Webhook::constructEvent()`.

### CRITICAL #2: Idempotency — таблиця або Redis
Stripe **гарантує at-least-once delivery**. Це означає, що ту саму подію ти отримаєш 2-3 рази (мережеві проблеми, retry policy). Без idempotency-перевірки клієнт отримає 60 днів замість 30.

### CRITICAL #3: Запис у таблицю idempotency ПІСЛЯ бізнес-логіки
(`stripe_processed_events` якщо тільки Stripe; `payment_processed_events` якщо 11A виконано)
Якщо записати ДО — і потім бізнес-логіка кидає виняток — Stripe ретраїть, але наша система каже «вже оброблено», і клієнт не отримає продовження. **Завжди** записуй після успіху.

### CRITICAL #4: 200 OK навіть на бізнес-помилки
Stripe реагує на 5xx як на «треба ретраїти». Якщо помилка фіксована (наприклад, vacancy_id не існує) — повторні спроби нічого не дадуть, лише засмітять логи. Логуй як `error`, надсилай у Sentry, повертай 200.

**Виняток:** на 4xx (invalid signature) — повертай 400. Stripe позначить webhook як зламаний, ти отримаєш email.

### CRITICAL #5: Розрізнення типів checkout
Якщо в проєкті будуть інші продукти через Stripe (преміум-акаунти, реклама) — без `metadata.type` ти не зможеш розрізнити їх у webhook. Це треба закладати **зараз**, поки checkout-ів мало.

### CRITICAL #6: `lockForUpdate()` у транзакції
Без локу: scheduler може між `find()` і `extend()` встигнути перевести в `expired`, а наш `extend()` цього не побачить (через те, що в моделі логіка враховує поточний статус). З `lockForUpdate()` — інші транзакції чекають на нашу.

### CRITICAL #7: Подія `VacancyExtended`, а не прямий виклик нотифікацій
Спокусливо одразу в webhook викликати `Notification::send($employer, new VacancyExtendedNotification(...))`. **Не треба.** Чому:

- Webhook повинен бути швидким (<5 секунд відповіді), інакше Stripe ретраїть.
- Telegram API може лежати → нотифікація фейлиться → весь webhook фейлиться → Stripe ретраїть → дубль.
- Подія ставиться в чергу через listener → webhook повертає 200 одразу → нотифікація летить асинхронно.

Listener для події (модуль 8) обов'язково реалізує `ShouldQueue`.

### CRITICAL #8: Refund для archived
Якщо клієнт оплатив, але вакансія archived (хтось встиг архівувати між кліком і оплатою) — гроші вже списані. Це треба refund-ити. У цьому модулі — лише log + alert; повний refund-механізм — окрема історія.

### CRITICAL #9: Що бачить користувач при failed webhook
Stripe redirect-ить на `success_url` миттєво після оплати. Webhook прилітає **окремо**, з затримкою 1-30 секунд. Це означає:

- Не показуй на success-сторінці «Вакансію продовжено!» — бо ще не точно. Покажи «Оплату прийнято, оновлюємо публікацію...» з polling-перевіркою через Livewire.
- Це окрема історія для модуля «Stripe Checkout flow», поза цим модулем.

---

## ✅ Очікуваний результат модуля

1. Міграція `create_stripe_processed_events_table` створена та виконана (якщо 11A **не** виконано; інакше пропускається).
2. Подія `App\Events\VacancyExtended` створена.
3. Контролер `StripeWebhookController` створено / оновлено.
4. Маршрут зареєстровано, CSRF виключено.
5. `config/services.php` має `stripe.webhook_secret`.
6. `.env.example` оновлено.
7. Канал логування `payments` додано в `config/logging.php`:
```php
'payments' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/payments.log'),
    'level'  => 'debug',
    'days'   => 90,  // 3 місяці — для аудиту платежів
],
```
8. Локальне тестування зі Stripe CLI пройдено успішно.
9. Звіт мені:
   ```
   Webhook налаштовано. Verified signature, idempotency через таблицю,
   подія VacancyExtended відправляється.

   Локальний тест зі Stripe CLI:
   - Тригер checkout.session.completed → вакансія #1 продовжена на 30 днів
   - Дублікат-тригер → ігнорується (200 'duplicate')
   - Невалідний підпис → 400

   Перейти до модуля 7 (Livewire countdown)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не використовуй `Cashier` (laravel/cashier-stripe) для одноразових платежів — він заточений під підписки. Прямий API простіший.
- ❌ Не парси webhook payload вручну через `json_decode($request->getContent())` — використовуй `Webhook::constructEvent()`, бо він робить і парсинг, і верифікацію.
- ❌ Не зберігай `STRIPE_WEBHOOK_SECRET` у БД чи коді — тільки в `.env`.
- ❌ Не додавай `auth` middleware на роут вебхука — Stripe не має сесії, він підписує запит секретом.
- ❌ Не реалізуй refund-логіку в цьому модулі — окремий епік.
- ❌ Не реалізуй Telegram-нотифікацію в цьому модулі — модуль 8 через `VacancyExtended` listener.
