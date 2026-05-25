# Фікс формату номера рахунку та призначення платежу

## Контекст

Платформа My Job — Laravel 13.4 + PHP 8.3, Livewire 3 Volt (Class API), PostgreSQL.
Вже реалізовано: `Invoice` model, `InvoiceStatus` Enum, `InvoiceService`, `InvoiceMatcherService`, Volt-компонент `billing/invoice`, PDF-шаблон.

## Що змінюємо

1. **Формат номера рахунку:** `INV-2026-00042` → `MJ-00003` (наскрізна нумерація без року)
2. **Призначення платежу:** фіксований шаблон з назвою тарифу та датою
3. **Таблиця послуг у PDF:** юридично коректне формулювання відповідно до КВЕД 63.11
4. **`InvoiceService::create()`:** приймає назву тарифу (`plan_name`)

---

## КРОК 1 — Розвідка (ОБОВ'ЯЗКОВО ПЕРЕД БУДЬ-ЯКИМИ ЗМІНАМИ)

```bash
cat app/Services/InvoiceService.php
cat app/Models/Invoice.php
cat resources/views/pdf/invoice.blade.php
cat resources/views/livewire/billing/invoice.blade.php
cat tests/Feature/Employer/InvoiceTest.php

# Перевірити модель тарифів
cat app/Models/Plan.php 2>/dev/null || echo "NOT FOUND"
grep -r "plan_name\|planName\|plan->name" app/Services/InvoiceService.php
```

Зупинись і покажи результати. Не рухайся далі без підтвердження.

---

## КРОК 2 — Міграція: нові поля в таблиці `invoices`

```bash
php artisan make:migration add_plan_name_to_invoices_table
```

```php
Schema::table('invoices', function (Blueprint $table) {
    $table->string('plan_name')->nullable()->after('order_id');
});
```

---

## КРОК 3 — Оновити модель `Invoice`

Додати `plan_name` до `$fillable`:

```php
protected $fillable = [
    'user_id', 'order_id', 'plan_name', 'invoice_number', 'amount', 'status',
    // ... решта без змін
];
```

---

## КРОК 4 — Оновити `InvoiceService`

### 4.1 Змінити сигнатуру `create()`

```php
public function create(
    User $user,
    int $amountKopecks,
    ?int $orderId = null,
    array $payerData = [],
    ?string $planName = null   // ← нове поле
): Invoice
```

### 4.2 Оновити `generateNumber()`

Формат: `MJ-XXXXX` — наскрізна нумерація без року, 5 цифр із лівим доповненням нулями.

```php
private function generateNumber(): string
{
    $lastSeq = Invoice::max('id') ?? 0;
    $seq = str_pad($lastSeq + 1, 5, '0', STR_PAD_LEFT);
    return "MJ-{$seq}";
}
```

> **Важливо:** `max('id')` використовується як проксі для наскрізної нумерації.
> Якщо потрібна абсолютно точна наскрізна нумерація незалежно від видалень —
> використати `Invoice::withTrashed()->max('id')` або окремий лічильник у `settings`.
> Для поточного завдання `max('id')` достатньо.

### 4.3 Оновити формування `payment_purpose`

```php
$date = now()->locale('uk')->isoFormat('D MMMM YYYY') . ' р.';

$purpose = "Послуги з розміщення інформації на веб-сайті My Job";
if ($planName) {
    $purpose .= ", тариф {$planName}";
}
$purpose .= ", рахунок № {$number} від {$date} без ПДВ";
```

### 4.4 Додати `plan_name` до `Invoice::create()`

```php
$invoice = Invoice::create([
    'user_id'         => $user->id,
    'order_id'        => $orderId,
    'plan_name'       => $planName,
    'invoice_number'  => $number,
    // ... решта без змін
    'payment_purpose' => $purpose,
]);
```

---

## КРОК 5 — Оновити PDF-шаблон

Файл: `resources/views/pdf/invoice.blade.php`

Змінити рядок у таблиці послуг (найменування послуги):

```html
{{-- Було: --}}
<td>Послуги платформи My Job (рекламне розміщення)</td>

{{-- Стало: --}}
<td>
    Послуги з розміщення інформації на веб-сайті My Job
    @if($invoice->plan_name)
        (тариф {{ $invoice->plan_name }})
    @endif
    (КВЕД 63.11), без ПДВ
</td>
```

---

## КРОК 6 — Оновити `InvoiceMatcherService`

Regex для пошуку нового формату номера у призначенні платежу банківської транзакції:

```php
// Було:
if (!preg_match('/(INV-\d{4}-\d{5})/', $description, $matches)) {

// Стало:
if (!preg_match('/(MJ-\d{5})/', $description, $matches)) {
```

---

## КРОК 7 — Оновити тести

Файл: `tests/Feature/Employer/InvoiceTest.php`

Замінити всі перевірки формату `INV-` на `MJ-`:

```php
// Було:
$this->assertStringStartsWith('INV-', $invoice->invoice_number);

// Стало:
$this->assertStringStartsWith('MJ-', $invoice->invoice_number);
```

Додати нові тест-кейси:

```php
#[Test]
public function invoice_number_has_correct_mj_format(): void
{
    $user = User::factory()->create(['role' => UserRole::Employer]);

    $invoice = app(InvoiceService::class)->create($user, 50000);

    $this->assertMatchesRegularExpression('/^MJ-\d{5}$/', $invoice->invoice_number);
}

#[Test]
public function payment_purpose_contains_plan_name_when_provided(): void
{
    $user = User::factory()->create(['role' => UserRole::Employer]);

    $invoice = app(InvoiceService::class)->create(
        user: $user,
        amountKopecks: 50000,
        planName: 'Старт'
    );

    $this->assertStringContainsString('тариф Старт', $invoice->payment_purpose);
    $this->assertStringContainsString('MJ-', $invoice->payment_purpose);
    $this->assertStringContainsString('без ПДВ', $invoice->payment_purpose);
}

#[Test]
public function payment_purpose_without_plan_name_is_still_valid(): void
{
    $user = User::factory()->create(['role' => UserRole::Employer]);

    $invoice = app(InvoiceService::class)->create($user, 50000);

    $this->assertStringContainsString('веб-сайті My Job', $invoice->payment_purpose);
    $this->assertStringContainsString('без ПДВ', $invoice->payment_purpose);
    $this->assertStringNotContainsString('тариф', $invoice->payment_purpose);
}

#[Test]
public function matcher_finds_invoice_by_new_mj_format(): void
{
    Event::fake([\App\Events\InvoicePaid::class]);

    $user    = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = app(InvoiceService::class)->create($user, 50000, planName: 'Старт');

    $statements = [
        [
            'id'          => 'mono-stmt-001',
            'description' => $invoice->payment_purpose,
            'amount'      => 50000,
        ],
    ];

    app(\App\Services\InvoiceMatcherService::class)->match($statements);

    $this->assertEquals(\App\Enums\InvoiceStatus::Paid, $invoice->fresh()->status);
}
```

Оновити `InvoiceFactory` — замінити формат номера:

```php
'invoice_number' => 'MJ-' . str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
```

---

## КРОК 8 — Запуск тестів

```bash
php artisan test tests/Feature/Employer/InvoiceTest.php --stop-on-failure
```

Очікувані результати: **всі PASS** (існуючі + 4 нові).

---

## Важливі обмеження

1. **Не переписувати** існуючі методи — тільки розширювати
2. **Volt-only** — жодних стандартних Livewire компонентів
3. **PHPUnit 12** з `#[Test]` атрибутами — без `/** @test */`
4. **`actingAs()`** викликати до `Volt::test()`, не в ланцюжку
5. **Локаль для дати** — `now()->locale('uk')->isoFormat('D MMMM YYYY')` потребує `nesbot/carbon` з українською локаллю (вже є в Laravel за замовчуванням)
