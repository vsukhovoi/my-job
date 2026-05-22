# Claude Code Prompt: Billing Module

## Контекст проекту

Платформа **My Job** — Laravel 11+, Livewire 3, Volt-компоненти, PHPUnit 12.
Платить тільки **Employer**. Seeker — завжди безкоштовно.
Існуючий UI: таб "Мої платежі" вже є в навігації Employer back-office.

---

## Архітектура (не змінювати без запиту)

```
SubscriptionPlan       — довідник тарифів (незмінний адміном)
EmployerSubscription   — активна підписка Employer
JobPosting             — вакансія (вже існує, додати promotion-поля)
PlanFeature enum       — що дозволено на кожному тарифі
SubscriptionService    — бізнес-логіка активації / перевірки
```

Платіжний шлюз на цьому етапі **не підключати** — активація через адміна вручну або тестовий bypass.

---

## Крок 1 — Enum та константи

**Підтвердь перед виконанням.**

### 1а. `App\Enums\PlanType`

```php
enum PlanType: string
{
    case Free    = 'free';
    case Start   = 'start';
    case Business = 'business';
    case Pro     = 'pro';
}
```

### 1б. `App\Enums\PlanFeature`

```php
enum PlanFeature: string
{
    case ActiveJobs        = 'active_jobs';        // int: 0 = необмежено
    case ApplicationsPerMonth = 'applications_per_month'; // int: 0 = необмежено
    case Analytics         = 'analytics';          // bool
    case MessageTemplates  = 'message_templates';  // bool
    case HotPerMonth       = 'hot_per_month';      // int
    case TopPerMonth       = 'top_per_month';      // int
    case ApiAccess         = 'api_access';         // bool
    case TeamMembers       = 'team_members';       // int: 0 = необмежено
}
```

---

## Крок 2 — Міграції

**Підтвердь перед виконанням.**

### 2а. `subscription_plans`

```php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->string('type')->unique();          // PlanType value
    $table->string('name');                    // 'Старт', 'Бізнес', 'Про'
    $table->text('description')->nullable();
    $table->unsignedInteger('price_monthly'); // у гривнях (копійки не потрібні)
    $table->json('features');                 // ['active_jobs' => 3, 'analytics' => false, ...]
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2б. `employer_subscriptions`

```php
Schema::create('employer_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained('subscription_plans');
    $table->string('status');                 // 'active' | 'expired' | 'cancelled'
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->timestamp('cancelled_at')->nullable();
    $table->string('payment_reference')->nullable(); // для майбутнього шлюзу
    $table->json('meta')->nullable();
    $table->timestamps();
});
```

### 2в. Додати до існуючої таблиці вакансій

```php
// Додати колонки до таблиці vacancies
$table->boolean('is_hot')->default(false);
$table->boolean('is_top')->default(false);
$table->timestamp('promoted_until')->nullable();
```

---

## Крок 3 — Моделі

**Підтвердь перед виконанням.**

### 3а. `SubscriptionPlan`

- `$casts`: `type => PlanType::class`, `features => 'array'`, `is_active => 'boolean'`
- метод `feature(PlanFeature $feature): mixed` — повертає значення з `features` json
- `hasMany(EmployerSubscription::class)`

### 3б. `EmployerSubscription`

- `$casts`: `starts_at`, `ends_at`, `cancelled_at` => `datetime`, `meta => 'array'`
- `belongsTo(SubscriptionPlan::class, 'plan_id')`
- `belongsTo(User::class)`
- scope `active()`: `status = 'active'` AND `ends_at > now()`

### 3в. Оновити модель `User` (Employer)

Додати:
```php
public function activeSubscription(): HasOne
{
    return $this->hasOne(EmployerSubscription::class)
        ->where('status', 'active')
        ->where('ends_at', '>', now())
        ->latest();
}

public function currentPlan(): ?SubscriptionPlan
{
    return $this->activeSubscription?->plan;
}

public function can(PlanFeature $feature): bool|int
{
    return $this->currentPlan()?->feature($feature) ?? false;
}
```

---

## Крок 4 — Seeder тарифів

**Підтвердь перед виконанням.**

Файл: `database/seeders/SubscriptionPlanSeeder.php`

Чотири записи:

```php
[
    'type' => 'free',
    'name' => 'Безкоштовний',
    'price_monthly' => 0,
    'features' => [
        'active_jobs' => 1,
        'applications_per_month' => 10,
        'analytics' => false,
        'message_templates' => false,
        'hot_per_month' => 0,
        'top_per_month' => 0,
        'api_access' => false,
        'team_members' => 1,
    ],
],
[
    'type' => 'start',
    'name' => 'Старт',
    'price_monthly' => 799,
    'features' => [
        'active_jobs' => 3,
        'applications_per_month' => 50,
        'analytics' => false,
        'message_templates' => false,
        'hot_per_month' => 0,
        'top_per_month' => 0,
        'api_access' => false,
        'team_members' => 1,
    ],
],
[
    'type' => 'business',
    'name' => 'Бізнес',
    'price_monthly' => 1990,
    'features' => [
        'active_jobs' => 10,
        'applications_per_month' => 0,
        'analytics' => true,
        'message_templates' => true,
        'hot_per_month' => 1,
        'top_per_month' => 0,
        'api_access' => false,
        'team_members' => 3,
    ],
],
[
    'type' => 'pro',
    'name' => 'Про',
    'price_monthly' => 4490,
    'features' => [
        'active_jobs' => 0,
        'applications_per_month' => 0,
        'analytics' => true,
        'message_templates' => true,
        'hot_per_month' => 3,
        'top_per_month' => 1,
        'api_access' => true,
        'team_members' => 0,
    ],
],
```

Додати `SubscriptionPlanSeeder` до `DatabaseSeeder`.

---

## Крок 5 — SubscriptionService

**Підтвердь перед виконанням.**

Файл: `app/Services/SubscriptionService.php`

```php
public function activate(User $employer, SubscriptionPlan $plan, int $months = 1): EmployerSubscription

public function cancel(EmployerSubscription $subscription): void

public function checkLimit(User $employer, PlanFeature $feature): bool
// повертає true якщо ліміт не вичерпано або feature = необмежено (0)

public function activeJobsCount(User $employer): int
// кількість поточних активних вакансій

public function canPublishJob(User $employer): bool
// checkLimit для active_jobs

public function getRemainingHot(User $employer): int
// скільки HOT-підняттів залишилось цього місяця
```

Логіка `activate()`:
1. Деактивувати попередню активну підписку якщо є (`status = 'cancelled'`)
2. Створити новий запис `EmployerSubscription` з `starts_at = now()`, `ends_at = now()->addMonths($months)`
3. Якщо план `free` — `ends_at = now()->addYears(100)` (вічний)

---

## Крок 6 — Middleware перевірки ліміту

**Підтвердь перед виконанням.**

Файл: `app/Http/Middleware/CheckJobPublishingLimit.php`

- Перевіряти через `SubscriptionService::canPublishJob(auth()->user())`
- Якщо ліміт вичерпано — повернути JSON `['message' => 'Ліміт вакансій вичерпано. Оновіть тариф.']` зі статусом 403
- Зареєструвати як named middleware `subscription.job_limit`

> Додати middleware до роуту створення вакансії. Знайти існуючий роут — **не створювати новий**.

---

## Крок 7 — Volt-компонент "Мої платежі"

**Підтвердь перед виконанням.**

Знайти існуючий Volt-компонент що відповідає табу "Мої платежі" в Employer back-office.
Якщо не існує — створити: `resources/views/livewire/employer/payments.blade.php`

Компонент має показувати:

**Секція 1 — Поточний тариф:**
- Назва плану, дата закінчення, статус (active / expired)
- Лічильники використання: вакансії (X з Y), HOT залишок
- Кнопка "Змінити тариф" → відкриває список планів

**Секція 2 — Список доступних планів:**
- Картки трьох тарифів з цінами і переліком features
- Кнопка "Активувати" → викликає `SubscriptionService::activate()` (тестовий bypass без оплати)
- Помітити поточний активний план

**Секція 3 — Історія підписок:**
- Таблиця: план, дата початку, дата закінчення, статус

Використовувати `state`, `computed` і `action` з Volt синтаксису.

---

## Крок 8 — PHPUnit тести

**Підтвердь перед виконанням.**

Файл: `tests/Feature/Billing/SubscriptionTest.php`

Синтаксис: `#[Test]` атрибут, `actingAs()` до Volt якщо потрібно.

```
1. employer_starts_with_free_plan_by_default
2. can_activate_paid_subscription
3. activation_cancels_previous_subscription
4. expired_subscription_blocks_job_publishing
5. start_plan_limits_active_jobs_to_three
6. business_plan_allows_unlimited_applications
7. pro_plan_has_unlimited_active_jobs
8. can_get_remaining_hot_promotions
```

Шаблон:
```php
#[Test]
public function start_plan_limits_active_jobs_to_three(): void
{
    $employer = User::factory()->create(['role' => UserRole::Employer]);
    $plan = SubscriptionPlan::where('type', PlanType::Start)->first();

    $this->app->make(SubscriptionService::class)
        ->activate($employer, $plan);

    $limit = $plan->feature(PlanFeature::ActiveJobs);
    expect($limit)->toBe(3);

    $this->actingAs($employer);
    expect(
        $this->app->make(SubscriptionService::class)->canPublishJob($employer)
    )->toBeTrue();
}
```

> Тести покладаються на seeder — запускати після `php artisan db:seed --class=SubscriptionPlanSeeder`. Або створити плани в `setUp()` через фабрику якщо seeder недоступний в тестовому середовищі.

---

## Крок 9 — Free план для нових Employer (опційно)

**Підтвердь перед виконанням.**

Якщо є `RegisteredUserCreated` або аналогічний Event при реєстрації Employer — додати Listener що автоматично активує Free план.

Якщо такого Event немає — **зупинитись і повідомити**, не створювати Event самостійно.

---

## Обмеження

- Не підключати платіжний шлюз (LiqPay, Fondy тощо)
- Не змінювати файли у `tests/Feature/Seeker/` і `tests/Feature/Sync/`
- Не перейменовувати існуючі поля у таблицях
- Таблиця вакансій: `vacancies` (не `jobs`)
- Якщо роут або компонент не знайдено — **зупинитись і повідомити**

---

## Очікуваний результат

```
✓ php artisan migrate — без помилок
✓ php artisan db:seed --class=SubscriptionPlanSeeder — 4 записи
✓ php artisan test tests/Feature/Billing/ — 8/8 зелені
✓ Існуючі тести tests/Feature/Seeker/ і tests/Feature/Sync/ — залишаються зеленими
✓ Таб "Мої платежі" показує поточний план і дозволяє активувати інший
```
