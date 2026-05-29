# Anonymous Publication Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Замкнути payment gate для анонімної публікації вакансії (599 ₴) — вакансія зберігається неактивною до оплати, активується через webhook; у Telegram-розсилці назва компанії прихована.

**Architecture:** Новий формат ордеру `anon_{vacancyId}_{suffix}` додається до `CheckoutService`; всі три gateway парсять його і повертають `PaymentResult` з полем `anonymousVacancyId`; `WebhookController` активує вакансію; на сторінці білінгу з'являється картка оплати коли є `session('anonymous_vacancy_id')` з неактивною вакансією.

**Tech Stack:** Laravel 13, Livewire Volt, PHPUnit 12 (`#[Test]`), MySQL

---

## File Map

| Файл | Дія |
|------|-----|
| `app/Enums/AddonType.php` | + `AnonymousPublication` case (599 ₴) |
| `app/Payments/DTOs/PaymentResult.php` | + `anonymousVacancyId: ?int` + `isAnonymousPublication()` |
| `app/Payments/CheckoutService.php` | + `createAnonymousPublicationCheckout()`, `buildAnonymousOrderId()`, `parseAnonymousOrderId()` |
| `app/Payments/Gateways/MonoPayGateway.php` | + `anon_` branch у `parseWebhook()` |
| `app/Payments/Gateways/LiqPayGateway.php` | + `anon_` branch у `parseWebhook()` |
| `app/Payments/Gateways/WayForPayGateway.php` | + `anon_` branch у `parseWebhook()` |
| `app/Http/Controllers/Payments/WebhookController.php` | + `processAnonymousActivation()`, оновити dispatch |
| `resources/views/livewire/pages/employer/vacancies/edit.blade.php` | `is_active: false` для нових anonymous вакансій |
| `resources/views/livewire/pages/employer/billing.blade.php` | картка оплати при `session('anonymous_vacancy_id')` |
| `resources/views/livewire/pages/employer/billing-checkout-addon.blade.php` | + `vacancyId` prop, `createAnonymousPublicationCheckout` |
| `app/Console/Commands/SendVacancyAlerts.php` | `display_company_name` замість `company->name` |
| `tests/Feature/Employer/AnonymousVacancyTest.php` | + тести payment gate |
| `tests/Feature/Employer/AddonCheckoutTest.php` | + тест anonymous checkout |

---

## Task 1: AddonType + PaymentResult

**Files:**
- Modify: `app/Enums/AddonType.php`
- Modify: `app/Payments/DTOs/PaymentResult.php`

- [ ] **Step 1: Додати `AnonymousPublication` до `AddonType`**

```php
// app/Enums/AddonType.php — додати до enum:
case AnonymousPublication = 'anonymous_publication';

// label():
self::AnonymousPublication => 'Анонімна публікація',

// price():
self::AnonymousPublication => 599,

// durationDays():
self::AnonymousPublication => 0, // використовує термін тарифу вакансії
```

- [ ] **Step 2: Додати `anonymousVacancyId` та `isAnonymousPublication()` до `PaymentResult`**

```php
// app/Payments/DTOs/PaymentResult.php
final readonly class PaymentResult
{
    public function __construct(
        public bool $isPaid,
        public string $gatewayName,
        public string $externalEventId,
        public string $orderId,
        public int $amountKopecks,
        public string $currency,
        public ?string $vacancyId,
        public ?int $days,
        public ?int $planId = null,
        public ?int $userId = null,
        public ?string $failureReason = null,
        public ?int $anonymousVacancyId = null,
    ) {}

    public function isPlanSubscription(): bool
    {
        return $this->planId !== null && $this->userId !== null;
    }

    public function isAnonymousPublication(): bool
    {
        return $this->anonymousVacancyId !== null;
    }
}
```

- [ ] **Step 3: Запустити тести — переконатися що нічого не зламалось**

```bash
php artisan test --filter=AnonymousVacancy
```
Очікувано: всі pass.

- [ ] **Step 4: Commit**

```bash
git add app/Enums/AddonType.php app/Payments/DTOs/PaymentResult.php
git commit -m "feat(payment): AddonType::AnonymousPublication + PaymentResult::anonymousVacancyId"
```

---

## Task 2: CheckoutService — anonymous order helpers

**Files:**
- Modify: `app/Payments/CheckoutService.php`
- Test: `tests/Feature/Employer/AddonCheckoutTest.php`

- [ ] **Step 1: Написати тест**

```php
// tests/Feature/Employer/AddonCheckoutTest.php — додати:

#[Test]
public function build_anonymous_order_id_has_correct_prefix(): void
{
    $orderId = CheckoutService::buildAnonymousOrderId(42);
    $this->assertStringStartsWith('anon_42_', $orderId);
}

#[Test]
public function parse_anonymous_order_id_returns_vacancy_id(): void
{
    $orderId = CheckoutService::buildAnonymousOrderId(42);
    $this->assertSame(42, CheckoutService::parseAnonymousOrderId($orderId));
}

#[Test]
public function parse_anonymous_order_id_returns_null_for_other_prefixes(): void
{
    $this->assertNull(CheckoutService::parseAnonymousOrderId('vac_42_30_abc123'));
    $this->assertNull(CheckoutService::parseAnonymousOrderId('sub_1_2_abc'));
}

#[Test]
public function checkout_service_creates_anonymous_publication_checkout(): void
{
    $employer = $this->makeEmployer();
    $vacancy  = \App\Models\Vacancy::factory()->create([
        'company_id' => $employer->company->id,
        'title'      => 'PHP Developer',
    ]);

    $gateway = $this->createMock(PaymentGateway::class);
    $gateway->method('name')->willReturn('mono');
    $gateway->method('createCheckout')->willReturnCallback(
        fn(CheckoutData $data) => 'https://pay.example.com/' . $data->orderId
    );

    $service = new CheckoutService($gateway);
    $url = $service->createAnonymousPublicationCheckout($vacancy, $employer);

    $this->assertStringStartsWith('https://', $url);
    $this->assertStringContainsString('anon_', $url);
}
```

- [ ] **Step 2: Запустити тест — переконатися що падає**

```bash
php artisan test --filter=AddonCheckoutTest
```
Очікувано: FAIL — `buildAnonymousOrderId`, `parseAnonymousOrderId`, `createAnonymousPublicationCheckout` не існують.

- [ ] **Step 3: Реалізувати методи в `CheckoutService`**

```php
// app/Payments/CheckoutService.php — додати методи:

public function createAnonymousPublicationCheckout(Vacancy $vacancy, User $user): string
{
    $orderId = self::buildAnonymousOrderId($vacancy->id);

    $data = new CheckoutData(
        amountKopecks: AddonType::AnonymousPublication->price() * 100,
        currency:      'UAH',
        orderId:       $orderId,
        description:   "Анонімна публікація вакансії «{$vacancy->title}»",
        successUrl:    route('employer.billing'),
        cancelUrl:     route('employer.billing'),
        webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
        vacancy:       $vacancy,
        userId:        $user->id,
    );

    return $this->gateway->createCheckout($data);
}

public static function buildAnonymousOrderId(int $vacancyId): string
{
    return sprintf('anon_%d_%s', $vacancyId, substr(uniqid(), -6));
}

public static function parseAnonymousOrderId(string $orderId): ?int
{
    if (preg_match('/^anon_(\d+)_/', $orderId, $m)) {
        return (int) $m[1];
    }
    return null;
}
```

Також додати `use App\Enums\AddonType;` у заголовку файлу якщо відсутній.

- [ ] **Step 4: Запустити тести**

```bash
php artisan test --filter=AddonCheckoutTest
```
Очікувано: всі pass.

- [ ] **Step 5: Commit**

```bash
git add app/Payments/CheckoutService.php tests/Feature/Employer/AddonCheckoutTest.php
git commit -m "feat(payment): anonymous publication order helpers у CheckoutService"
```

---

## Task 3: Gateway webhook parsing — `anon_` prefix

**Files:**
- Modify: `app/Payments/Gateways/MonoPayGateway.php`
- Modify: `app/Payments/Gateways/LiqPayGateway.php`
- Modify: `app/Payments/Gateways/WayForPayGateway.php`

У кожному gateway у методі `parseWebhook()` додати блок **перед** існуючим `parseOrderId` fallthrough. Патерн ідентичний у всіх трьох.

- [ ] **Step 1: `MonoPayGateway::parseWebhook()` — додати `anon_` branch**

```php
// app/Payments/Gateways/MonoPayGateway.php
// Знайти рядок: [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);
// Замінити весь блок parseOrderId + return на:

if (str_starts_with($orderId, 'anon_')) {
    $anonymousVacancyId = CheckoutService::parseAnonymousOrderId($orderId);

    return new PaymentResult(
        isPaid:               $isPaid,
        gatewayName:          $this->name(),
        externalEventId:      $data['invoiceId'] ?? uniqid('mono_', true),
        orderId:              $orderId,
        amountKopecks:        (int) ($data['amount'] ?? 0),
        currency:             'UAH',
        vacancyId:            null,
        days:                 null,
        anonymousVacancyId:   $anonymousVacancyId,
        failureReason:        $isPaid ? null : "status={$status}",
    );
}

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
```

- [ ] **Step 2: `LiqPayGateway::parseWebhook()` — додати `anon_` branch**

```php
// app/Payments/Gateways/LiqPayGateway.php
// Після блоку str_starts_with($orderId, 'sub_'), перед parseOrderId:

if (str_starts_with($orderId, 'anon_')) {
    $anonymousVacancyId = CheckoutService::parseAnonymousOrderId($orderId);

    return new PaymentResult(
        isPaid:               $isPaid,
        gatewayName:          $this->name(),
        externalEventId:      $eventId,
        orderId:              $orderId,
        amountKopecks:        $amountKopecks,
        currency:             $decoded['currency'] ?? 'UAH',
        vacancyId:            null,
        days:                 null,
        anonymousVacancyId:   $anonymousVacancyId,
        failureReason:        $isPaid ? null : ($decoded['err_description'] ?? "status={$status}"),
    );
}
```

- [ ] **Step 3: `WayForPayGateway::parseWebhook()` — додати `anon_` branch**

```php
// app/Payments/Gateways/WayForPayGateway.php
// Після блоку str_starts_with($orderId, 'sub_'), перед parseOrderId:

if (str_starts_with($orderId, 'anon_')) {
    $anonymousVacancyId = CheckoutService::parseAnonymousOrderId($orderId);

    return new PaymentResult(
        isPaid:               $isPaid,
        gatewayName:          $this->name(),
        externalEventId:      $eventId,
        orderId:              $orderId,
        amountKopecks:        $amountKopecks,
        currency:             $data['currency'] ?? 'UAH',
        vacancyId:            null,
        days:                 null,
        anonymousVacancyId:   $anonymousVacancyId,
        failureReason:        $isPaid ? null : "status={$transactionStatus}",
    );
}
```

- [ ] **Step 4: Запустити тести**

```bash
php artisan test
```
Очікувано: всі pass.

- [ ] **Step 5: Commit**

```bash
git add app/Payments/Gateways/
git commit -m "feat(payment): парсинг anon_ ордеру у всіх трьох gateway"
```

---

## Task 4: WebhookController — processAnonymousActivation

**Files:**
- Modify: `app/Http/Controllers/Payments/WebhookController.php`
- Test: `tests/Feature/Employer/AnonymousVacancyTest.php`

- [ ] **Step 1: Написати тест активації**

```php
// tests/Feature/Employer/AnonymousVacancyTest.php — додати:

use App\Payments\DTOs\PaymentResult;
use App\Http\Controllers\Payments\WebhookController;
use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;

#[Test]
public function webhook_activates_anonymous_vacancy_on_paid_result(): void
{
    $this->vacancy->update([
        'publication_type' => VacancyPublicationType::Anonymous,
        'is_active'        => false,
    ]);

    $gateway = $this->createMock(PaymentGateway::class);
    $gateway->method('name')->willReturn('mono');
    $gateway->method('parseWebhook')->willReturn(new PaymentResult(
        isPaid:             true,
        gatewayName:        'mono',
        externalEventId:    'evt_test_anon_001',
        orderId:            'anon_' . $this->vacancy->id . '_abc123',
        amountKopecks:      59900,
        currency:           'UAH',
        vacancyId:          null,
        days:               null,
        anonymousVacancyId: $this->vacancy->id,
    ));
    $gateway->method('successResponse')->willReturn(response(''));

    $registry = $this->createMock(PaymentGatewayRegistry::class);
    $registry->method('get')->willReturn($gateway);

    $this->app->instance(PaymentGatewayRegistry::class, $registry);

    $this->postJson('/webhooks/payments/mono', []);

    $this->assertTrue($this->vacancy->fresh()->is_active);
}

#[Test]
public function webhook_does_not_activate_vacancy_when_payment_failed(): void
{
    $this->vacancy->update([
        'publication_type' => VacancyPublicationType::Anonymous,
        'is_active'        => false,
    ]);

    $gateway = $this->createMock(PaymentGateway::class);
    $gateway->method('name')->willReturn('mono');
    $gateway->method('parseWebhook')->willReturn(new PaymentResult(
        isPaid:             false,
        gatewayName:        'mono',
        externalEventId:    'evt_test_anon_002',
        orderId:            'anon_' . $this->vacancy->id . '_abc456',
        amountKopecks:      59900,
        currency:           'UAH',
        vacancyId:          null,
        days:               null,
        anonymousVacancyId: $this->vacancy->id,
        failureReason:      'status=failure',
    ));
    $gateway->method('successResponse')->willReturn(response(''));

    $registry = $this->createMock(PaymentGatewayRegistry::class);
    $registry->method('get')->willReturn($gateway);

    $this->app->instance(PaymentGatewayRegistry::class, $registry);

    $this->postJson('/webhooks/payments/mono', []);

    $this->assertFalse($this->vacancy->fresh()->is_active);
}
```

- [ ] **Step 2: Запустити тести — переконатися що падають**

```bash
php artisan test --filter=webhook_activates_anonymous
```
Очікувано: FAIL.

- [ ] **Step 3: Додати `processAnonymousActivation()` та оновити dispatch у `WebhookController`**

```php
// app/Http/Controllers/Payments/WebhookController.php
// Замінити блок dispatch в методі handle():

try {
    if ($result->isAnonymousPublication()) {
        $this->processAnonymousActivation($result, $gateway);
    } elseif ($result->isPlanSubscription()) {
        $this->processPlanSubscription($result, $gateway);
    } else {
        $this->processExtension($result, $gateway);
    }
} catch (\Throwable $e) { ... }

// Додати новий приватний метод:
private function processAnonymousActivation(PaymentResult $result, string $gateway): void
{
    if (! $result->anonymousVacancyId) {
        throw new \UnexpectedValueException(
            "Cannot extract vacancy_id from orderId={$result->orderId}"
        );
    }

    DB::transaction(function () use ($result, $gateway): void {
        $vacancy = Vacancy::lockForUpdate()->find($result->anonymousVacancyId);

        if (! $vacancy) {
            throw new \DomainException("Vacancy {$result->anonymousVacancyId} not found");
        }

        $vacancy->update(['is_active' => true]);

        Log::channel('payments')->info("Anonymous vacancy activated [{$gateway}]", [
            'vacancy_id' => $vacancy->id,
            'event_id'   => $result->externalEventId,
        ]);
    });
}
```

- [ ] **Step 4: Запустити тести**

```bash
php artisan test --filter=AnonymousVacancy
```
Очікувано: всі pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Payments/WebhookController.php tests/Feature/Employer/AnonymousVacancyTest.php
git commit -m "feat(payment): WebhookController::processAnonymousActivation"
```

---

## Task 5: Vacancy edit form — payment gate

**Files:**
- Modify: `resources/views/livewire/pages/employer/vacancies/edit.blade.php`

- [ ] **Step 1: Змінити логіку `is_active` при збереженні**

```php
// edit.blade.php — у методі save(), знайти:
'is_active' => true,

// Замінити на:
'is_active' => $this->publicationType !== 'anonymous'
    ? true
    : ($this->vacancyId && $vacancy?->is_active ? true : false),
```

Важливо: для нових anonymous вакансій (`!$this->vacancyId`) → `is_active: false`.
Для редагування вже активної anonymous вакансії → `is_active: true` (залишаємо).
При зміні з anonymous на standard → `is_active: true`.

Повна логіка:
```php
$isAnonymous = $this->publicationType === 'anonymous';

// У масиві $data:
'is_active' => match(true) {
    ! $isAnonymous                           => true,   // standard — завжди активна
    $this->vacancyId && $vacancy->is_active  => true,   // редагування вже оплаченої anonymous
    default                                  => false,  // нова або неоплачена anonymous
},
```

Де `$vacancy` — це `Vacancy::where('company_id', $company->id)->findOrFail($this->vacancyId)` (вже є в коді для edit case).
Для create case `$vacancy` буде `null`, тому `$this->vacancyId && $vacancy->is_active` = false.

- [ ] **Step 2: Запустити тести**

```bash
php artisan test --filter=AnonymousVacancy
```
Очікувано: всі pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/pages/employer/vacancies/edit.blade.php
git commit -m "feat(vacancy): is_active=false для нових anonymous вакансій (payment gate)"
```

---

## Task 6: Billing UI — картка оплати + checkout компонент

**Files:**
- Modify: `resources/views/livewire/pages/employer/billing.blade.php`
- Modify: `resources/views/livewire/pages/employer/billing-checkout-addon.blade.php`

- [ ] **Step 1: Оновити anonymous картку в `billing.blade.php`**

Знайти існуючий блок `{{-- Анонімна публікація --}}` і замінити на:

```blade
{{-- Анонімна публікація --}}
@php
    $pendingAnonId = session('anonymous_vacancy_id');
    $pendingAnonVacancy = $pendingAnonId
        ? \App\Models\Vacancy::find($pendingAnonId)
        : null;
    $hasPendingAnon = $pendingAnonVacancy && ! $pendingAnonVacancy->is_active;
@endphp
<div class="flex items-center gap-3 p-3 border {{ $hasPendingAnon ? 'border-purple-300 bg-purple-50/40' : 'border-gray-100' }} rounded-xl hover:border-purple-200 hover:bg-purple-50/30 transition-colors">
    <span class="text-2xl shrink-0">🕵️</span>
    <div class="flex-1 min-w-0">
        <p class="font-semibold text-gray-900 text-sm">Анонімна публікація</p>
        @if($hasPendingAnon)
            <p class="text-xs text-purple-600">Вакансія «{{ $pendingAnonVacancy->title }}» очікує оплати</p>
        @else
            <p class="text-xs text-gray-500">Публікація без бренду компанії · 599 ₴</p>
        @endif
    </div>
    <div class="flex items-center gap-2 shrink-0">
        @if($hasPendingAnon)
            <a href="{{ route('employer.billing.checkout.addon', ['addon' => 'anonymous_publication']) }}?vacancy_id={{ $pendingAnonId }}"
               class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                Оплатити 599 ₴
            </a>
        @else
            <span class="text-sm font-bold text-gray-800">599 ₴</span>
            <a href="{{ route('employer.vacancies.create') }}"
               class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                Створити
            </a>
        @endif
    </div>
</div>
```

- [ ] **Step 2: Додати `vacancyId` prop та anonymous логіку в `billing-checkout-addon.blade.php`**

```php
// PHP секція компонента — замінити клас:
new #[Layout('layouts.app')] class extends Component
{
    public AddonType $addon;
    public ?int $vacancyId = null;

    public function mount(AddonType $addon): void
    {
        $this->addon = $addon;
        $this->vacancyId = request()->integer('vacancy_id') ?: null;
    }

    public function pay(string $gateway): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gw = $registry->get($gateway);

        abort_if($gw === null, 422, "Невідомий шлюз: {$gateway}");

        $checkout = new CheckoutService($gw);

        if ($this->addon === AddonType::AnonymousPublication && $this->vacancyId) {
            $vacancy = \App\Models\Vacancy::findOrFail($this->vacancyId);
            $url = $checkout->createAnonymousPublicationCheckout($vacancy, auth()->user());
        } else {
            $url = $checkout->createAddonCheckout($this->addon, auth()->user());
        }

        $this->redirect($url, navigate: false);
    }

    public function payByIban(): void
    {
        $label = $this->addon === AddonType::AnonymousPublication && $this->vacancyId
            ? "Анонімна публікація вакансії #{$this->vacancyId}"
            : $this->addon->label();

        $invoice = app(InvoiceService::class)->create(
            auth()->user(),
            (int) ($this->addon->price() * 100),
            planName: $label,
        );

        $this->redirect(route('employer.billing.invoice.show', $invoice->invoice_number), navigate: false);
    }
}
```

Також додати `use App\Enums\AddonType;` і `use App\Models\Vacancy;` якщо відсутні у заголовку PHP секції.

- [ ] **Step 3: Оновити summary блок в шаблоні checkout для anonymous**

```blade
{{-- Addon summary — знайти і замінити заголовок: --}}
<div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6 text-center">
    <p class="text-sm text-gray-500 mb-1">Ви обрали послугу</p>
    <h1 class="text-2xl font-extrabold text-gray-900">
        {{ $addon === \App\Enums\AddonType::AnonymousPublication && $vacancyId
            ? 'Анонімна публікація'
            : $addon->label() }}
    </h1>
    <p class="text-3xl font-bold text-blue-600 mt-2">
        {{ number_format($addon->price(), 0, '.', ' ') }} ₴
    </p>
    @if($addon === \App\Enums\AddonType::AnonymousPublication)
        <p class="text-sm text-gray-500 mt-1">На весь термін публікації вакансії</p>
    @else
        <p class="text-sm text-gray-500 mt-1">Термін дії: {{ $addon->durationDays() }} днів</p>
    @endif
</div>
```

- [ ] **Step 4: Запустити тести**

```bash
php artisan test --filter=AddonCheckout
```
Очікувано: всі pass.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/pages/employer/billing.blade.php \
        resources/views/livewire/pages/employer/billing-checkout-addon.blade.php
git commit -m "feat(billing): картка оплати anonymous + checkout компонент"
```

---

## Task 7: SendVacancyAlerts — anonymous name fix

**Files:**
- Modify: `app/Console/Commands/SendVacancyAlerts.php`
- Test: новий тест у `tests/Feature/Employer/AnonymousVacancyTest.php`

- [ ] **Step 1: Написати тест**

```php
// tests/Feature/Employer/AnonymousVacancyTest.php — додати:

use App\Services\TelegramNotifier;

#[Test]
public function send_vacancy_alerts_hides_company_name_for_anonymous(): void
{
    $subscription = \App\Models\TelegramSubscription::factory()->create([
        'category_id' => $this->vacancy->category_id,
        'telegram_id' => '999999999',
    ]);

    $this->vacancy->update([
        'publication_type' => VacancyPublicationType::Anonymous,
        'anonymous_name'   => 'Велика Компанія',
        'is_active'        => true,
        'published_at'     => now(),
    ]);

    $notifier = $this->createMock(TelegramNotifier::class);
    $notifier->expects($this->once())
        ->method('send')
        ->with(
            '999999999',
            $this->callback(fn(string $text) =>
                str_contains($text, 'Велика Компанія') &&
                ! str_contains($text, 'ТОВ "Тестова Компанія"')
            )
        );

    $this->app->instance(TelegramNotifier::class, $notifier);

    $this->artisan('app:send-vacancy-alerts')->assertSuccessful();
}

#[Test]
public function send_vacancy_alerts_shows_real_company_name_for_standard(): void
{
    \App\Models\TelegramSubscription::factory()->create([
        'category_id' => $this->vacancy->category_id,
        'telegram_id' => '888888888',
    ]);

    $this->vacancy->update([
        'is_active'    => true,
        'published_at' => now(),
    ]);

    $notifier = $this->createMock(TelegramNotifier::class);
    $notifier->expects($this->once())
        ->method('send')
        ->with(
            '888888888',
            $this->callback(fn(string $text) =>
                str_contains($text, 'ТОВ "Тестова Компанія"')
            )
        );

    $this->app->instance(TelegramNotifier::class, $notifier);

    $this->artisan('app:send-vacancy-alerts')->assertSuccessful();
}
```

- [ ] **Step 2: Запустити тести — переконатися що падають**

```bash
php artisan test --filter=send_vacancy_alerts_hides
```
Очікувано: FAIL.

- [ ] **Step 3: Виправити `SendVacancyAlerts`**

```php
// app/Console/Commands/SendVacancyAlerts.php
// Рядки 53-59 — замінити на:

$location = ! $vacancy->isAnonymous() && $vacancy->company->location
    ? " · {$vacancy->company->location}"
    : '';

$text = "🆕 <b>Нова вакансія у категорії {$vacancy->category->name}</b>\n\n"
    . "📌 <b>{$vacancy->title}</b>\n"
    . "🏭 {$vacancy->display_company_name}{$location}"
    . $salary
    . "\n\n<a href=\"" . rtrim(config('app.url'), '/') . "/jobs/{$vacancy->slug}\">👉 Переглянути вакансію</a>";
```

- [ ] **Step 4: Запустити всі тести**

```bash
php artisan test --filter=AnonymousVacancy
```
Очікувано: всі pass.

- [ ] **Step 5: Commit та push**

```bash
git add app/Console/Commands/SendVacancyAlerts.php tests/Feature/Employer/AnonymousVacancyTest.php
git commit -m "fix(alerts): приховати назву компанії у Telegram-розсилці для анонімних вакансій"
git push
```

---

## Self-Review

**Spec coverage:**
- ✅ `AddonType::AnonymousPublication` (599 ₴) — Task 1
- ✅ `PaymentResult::anonymousVacancyId + isAnonymousPublication()` — Task 1
- ✅ `CheckoutService::createAnonymousPublicationCheckout` — Task 2
- ✅ `anon_` парсинг у всіх 3 gateway — Task 3
- ✅ `WebhookController::processAnonymousActivation` — Task 4
- ✅ `is_active: false` для нових anonymous вакансій — Task 5
- ✅ Картка оплати на billing — Task 6
- ✅ Checkout компонент з `vacancyId` — Task 6
- ✅ `SendVacancyAlerts` — `display_company_name` — Task 7

**Gaps:** немає.

**Type consistency:**
- `anonymousVacancyId: ?int` — узгоджено між Task 1, 3, 4
- `parseAnonymousOrderId(): ?int` — узгоджено між Task 2, 3
- `createAnonymousPublicationCheckout(Vacancy, User): string` — узгоджено між Task 2, 6
- `AddonType::AnonymousPublication` — узгоджено між Task 1, 5, 6
