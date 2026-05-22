# Скасування рахунку клієнтом

## Контекст

Платформа My Job — Laravel 13.4 + PHP 8.3, Livewire 3 Volt (Class API), FilamentPHP v4, PostgreSQL.
Вже реалізовано: `Invoice` model, `InvoiceStatus` Enum (pending/paid/expired/cancelled), `InvoiceService`, Volt-компонент `billing/invoice`.

## Завдання

Додати можливість роботодавцю скасовувати рахунок зі статусом `pending`. Фізичне видалення заборонено — тільки `status = cancelled`.

---

## КРОК 1 — Розвідка (ОБОВ'ЯЗКОВО ПЕРЕД БУДЬ-ЯКИМИ ЗМІНАМИ)

```bash
cat app/Services/InvoiceService.php
cat resources/views/livewire/billing/invoice.blade.php
cat app/Enums/InvoiceStatus.php
cat tests/Feature/Employer/InvoiceTest.php
```

Зупинись і покажи результати. Не рухайся далі без підтвердження.

---

## КРОК 2 — Додати метод `cancel()` в `InvoiceService`

Розширити існуючий `app/Services/InvoiceService.php` — додати метод:

```php
/**
 * Скасування рахунку клієнтом.
 * Дозволено тільки для статусу pending.
 * Фізичне видалення заборонено.
 *
 * @throws \Illuminate\Auth\Access\AuthorizationException
 */
public function cancel(Invoice $invoice, User $user): void
{
    if ($invoice->user_id !== $user->id) {
        throw new \Illuminate\Auth\Access\AuthorizationException('Немає доступу до цього рахунку.');
    }

    if ($invoice->status !== InvoiceStatus::Pending) {
        throw new \InvalidArgumentException('Скасувати можна лише рахунок зі статусом «Очікує оплати».');
    }

    $invoice->update(['status' => InvoiceStatus::Cancelled]);
}
```

---

## КРОК 3 — Оновити Volt-компонент `billing/invoice`

Розширити існуючий `resources/views/livewire/billing/invoice.blade.php` — додати метод `cancel()` та кнопку в шаблон.

### PHP-секція — додати метод:

```php
public function cancel(): void
{
    $user = auth()->user();

    try {
        app(InvoiceService::class)->cancel($this->invoice, $user);
        $this->invoice->refresh();
        session()->flash('success', 'Рахунок скасовано.');
    } catch (\InvalidArgumentException $e) {
        session()->flash('error', $e->getMessage());
    }
}
```

### Blade-секція — додати кнопку «Скасувати рахунок»

Кнопка відображається **тільки** якщо `$invoice->status === InvoiceStatus::Pending`.
Розмістити поруч з кнопкою «Завантажити PDF» (в тому ж flex-контейнері).
Використовувати Alpine.js для підтвердження дії перед викликом.

```html
@if($invoice->status === \App\Enums\InvoiceStatus::Pending)
    <div x-data="{ confirm: false }">
        <button
            x-show="!confirm"
            x-on:click="confirm = true"
            class="flex items-center gap-2 px-4 py-2.5 border border-red-300 text-red-600 rounded-xl text-sm font-medium hover:bg-red-50 transition-colors"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Скасувати рахунок
        </button>

        <div x-show="confirm" x-cloak class="flex items-center gap-2">
            <span class="text-sm text-red-600">Впевнені?</span>
            <button
                wire:click="cancel"
                class="px-3 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition-colors"
            >
                Так, скасувати
            </button>
            <button
                x-on:click="confirm = false"
                class="px-3 py-2 border border-gray-300 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors"
            >
                Ні
            </button>
        </div>
    </div>
@endif
```

Додати відображення flash-повідомлень у шаблоні (якщо ще немає):

```html
@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
        {{ session('error') }}
    </div>
@endif
```

---

## КРОК 4 — Додати action «Скасувати» у Filament `InvoiceResource`

Розширити існуючий `app/Filament/Resources/InvoiceResource.php` — додати action поруч з «Підтвердити оплату»:

```php
Tables\Actions\Action::make('cancel_invoice')
    ->label('Скасувати')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->visible(fn (Invoice $record) => $record->status === \App\Enums\InvoiceStatus::Pending)
    ->requiresConfirmation()
    ->modalHeading('Скасувати рахунок')
    ->modalDescription('Рахунок буде переведено в статус «Скасовано». Цю дію не можна відмінити.')
    ->action(function (Invoice $record) {
        $record->update(['status' => \App\Enums\InvoiceStatus::Cancelled]);
        \Filament\Notifications\Notification::make()
            ->title('Рахунок скасовано')
            ->success()
            ->send();
    }),
```

---

## КРОК 5 — Тести

Додати нові тест-кейси до існуючого `tests/Feature/Employer/InvoiceTest.php`:

```php
#[Test]
public function employer_can_cancel_pending_invoice(): void
{
    $user    = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'status'  => InvoiceStatus::Pending,
    ]);

    app(InvoiceService::class)->cancel($invoice, $user);

    $this->assertEquals(InvoiceStatus::Cancelled, $invoice->fresh()->status);
}

#[Test]
public function employer_cannot_cancel_paid_invoice(): void
{
    $user    = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'status'  => InvoiceStatus::Paid,
    ]);

    $this->expectException(\InvalidArgumentException::class);
    app(InvoiceService::class)->cancel($invoice, $user);
}

#[Test]
public function employer_cannot_cancel_another_users_invoice(): void
{
    $owner = User::factory()->create(['role' => UserRole::Employer]);
    $other = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = Invoice::factory()->create([
        'user_id' => $owner->id,
        'status'  => InvoiceStatus::Pending,
    ]);

    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
    app(InvoiceService::class)->cancel($invoice, $other);
}

#[Test]
public function cancel_button_visible_for_pending_invoice_in_volt(): void
{
    $user    = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'status'  => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user);
    Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
        ->assertSee('Скасувати рахунок');
}

#[Test]
public function cancel_button_hidden_for_paid_invoice_in_volt(): void
{
    $user    = User::factory()->create(['role' => UserRole::Employer]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'status'  => InvoiceStatus::Paid,
    ]);

    $this->actingAs($user);
    Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
        ->assertDontSee('Скасувати рахунок');
}
```

---

## КРОК 6 — Запуск тестів

```bash
php artisan test tests/Feature/Employer/InvoiceTest.php --stop-on-failure
```

Очікувані результати: **12/12 PASS** (7 існуючих + 5 нових).

---

## Важливі обмеження

1. **Не переписувати** існуючі методи `InvoiceService` — тільки додати `cancel()`
2. **Не переписувати** Volt-компонент — тільки розширити
3. **Volt-only** — жодних стандартних Livewire компонентів
4. **PHPUnit 12** з `#[Test]` атрибутами — без `/** @test */`
5. **`actingAs()`** викликати до `Volt::test()`, не в ланцюжку
6. **Фізичне видалення заборонено** — тільки `status = cancelled`
