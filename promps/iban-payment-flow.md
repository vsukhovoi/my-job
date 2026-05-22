# Реалізація IBAN-платежів (Monobank Corporate API)

## Контекст

Платформа My Job (myjob.co.ua) — Laravel 13.4 + PHP 8.3, Livewire 3 Volt (Class API), Tailwind CSS, FilamentPHP v4, PostgreSQL.

Реалізуємо повний flow оплати по IBAN для роботодавців (B2B: ТОВ / ФОП):
- Генерація рахунку (invoice) з унікальним номером
- PDF рахунку через barryvdh/laravel-dompdf
- Веб-сторінка рахунку (Volt component)
- Автоматичний матчинг платежів через Monobank Corporate API (наразі — заглушка)
- Команда polling кожні 5 хвилин
- Ручне підтвердження через Filament

---

## КРОК 1 — Розвідка (ОБОВ'ЯЗКОВО ПЕРЕД БУДЬ-ЯКИМИ ЗМІНАМИ)

Перед написанням будь-якого коду виконай:

```bash
# Перевір існуючий білінг
cat app/Services/CheckoutService.php
cat app/Enums/AddonType.php
ls app/Models/ | grep -i invoice
ls app/Models/ | grep -i order
cat app/Models/Order.php 2>/dev/null || echo "NOT FOUND"

# Перевір міграції
ls database/migrations/ | grep -E "order|invoice|billing"

# Перевір існуючі payment services
ls app/Services/ | grep -iE "pay|billing|checkout|mono"

# Перевір composer
cat composer.json | grep -E "dompdf|pdf|barryvdh"

# Перевір routes
cat routes/web.php | grep -i billing
cat routes/web.php | grep -i invoice

# Перевір конфіги
cat config/services.php | grep -i mono 2>/dev/null || echo "No mono config"
```

Зупинись і покажи результати. Не рухайся далі без підтвердження.

---

## КРОК 2 — Встановлення залежності

```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider" --tag=config
```

---

## КРОК 3 — Міграція `invoices`

Створи міграцію:

```bash
php artisan make:migration create_invoices_table
```

Вміст міграції:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
    $table->string('invoice_number')->unique(); // INV-2026-00042
    $table->unsignedBigInteger('amount');        // в копійках
    $table->string('status')->default('pending'); // InvoiceStatus enum
    
    // Реквізити платника (заповнює роботодавець при замовленні)
    $table->string('payer_name')->nullable();     // Назва компанії/ФОП
    $table->string('payer_edrpou')->nullable();   // ЄДРПОУ / ІПН
    
    // Реквізити отримувача (з .env)
    $table->string('recipient_name');             // ТОВ «ФЛАГМАН СВ»
    $table->string('iban');                       // UA...
    $table->string('edrpou');                     // 37490783
    $table->string('bank_name');                  // АТ «УНІВЕРСАЛ БАНК»
    $table->string('mfo')->nullable();
    
    // Призначення платежу
    $table->string('payment_purpose');            // "Оплата послуг My Job, рахунок INV-2026-00042"
    
    // Підтвердження
    $table->string('monobank_statement_id')->nullable()->unique();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('expires_at')->nullable();  // +30 днів від створення
    
    $table->timestamps();
});
```

---

## КРОК 4 — Enum `InvoiceStatus`

Файл: `app/Enums/InvoiceStatus.php`

```php
<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Expired   = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Очікує оплати',
            self::Paid      => 'Оплачено',
            self::Expired   => 'Прострочено',
            self::Cancelled => 'Скасовано',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending   => 'yellow',
            self::Paid      => 'green',
            self::Expired   => 'gray',
            self::Cancelled => 'red',
        };
    }
}
```

---

## КРОК 5 — Модель `Invoice`

Файл: `app/Models/Invoice.php`

```php
<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'invoice_number', 'amount', 'status',
        'payer_name', 'payer_edrpou',
        'recipient_name', 'iban', 'edrpou', 'bank_name', 'mfo',
        'payment_purpose', 'monobank_statement_id', 'paid_at', 'expires_at',
    ];

    protected $casts = [
        'status'   => InvoiceStatus::class,
        'paid_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === InvoiceStatus::Expired
            || ($this->expires_at && $this->expires_at->isPast() && !$this->isPaid());
    }

    /** Сума в гривнях для відображення */
    public function amountFormatted(): string
    {
        return number_format($this->amount / 100, 2, '.', ' ') . ' грн';
    }
}
```

---

## КРОК 6 — Сервіс `InvoiceService`

Файл: `app/Services/InvoiceService.php`

```php
<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(User $user, int $amountKopecks, ?int $orderId = null, array $payerData = []): Invoice
    {
        return DB::transaction(function () use ($user, $amountKopecks, $orderId, $payerData) {
            $number = $this->generateNumber();

            $invoice = Invoice::create([
                'user_id'        => $user->id,
                'order_id'       => $orderId,
                'invoice_number' => $number,
                'amount'         => $amountKopecks,
                'status'         => InvoiceStatus::Pending,

                'payer_name'     => $payerData['payer_name'] ?? $user->company?->name,
                'payer_edrpou'   => $payerData['payer_edrpou'] ?? $user->company?->edrpou,

                'recipient_name' => config('invoice.recipient_name'),
                'iban'           => config('invoice.iban'),
                'edrpou'         => config('invoice.edrpou'),
                'bank_name'      => config('invoice.bank_name'),
                'mfo'            => config('invoice.mfo'),

                'payment_purpose' => "Оплата послуг My Job, рахунок {$number}",
                'expires_at'      => now()->addDays(30),
            ]);

            return $invoice;
        });
    }

    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');
    }

    public function markAsPaid(Invoice $invoice, ?string $statementId = null): void
    {
        $invoice->update([
            'status'                 => InvoiceStatus::Paid,
            'paid_at'                => now(),
            'monobank_statement_id'  => $statementId,
        ]);

        event(new \App\Events\InvoicePaid($invoice));
    }

    private function generateNumber(): string
    {
        $year    = now()->year;
        $lastId  = Invoice::whereYear('created_at', $year)->max('id') ?? 0;
        $seq     = str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
        return "INV-{$year}-{$seq}";
    }
}
```

---

## КРОК 7 — Конфіг `config/invoice.php`

```php
<?php

return [
    'recipient_name' => env('INVOICE_RECIPIENT_NAME', 'ТОВ «ФЛАГМАН СВ»'),
    'iban'           => env('INVOICE_IBAN', 'UA000000000000000000000000000'),
    'edrpou'         => env('INVOICE_EDRPOU', '37490783'),
    'bank_name'      => env('INVOICE_BANK_NAME', ''),
    'mfo'            => env('INVOICE_MFO', ''),
];
```

Додай до `.env` та `.env.example`:

```
INVOICE_RECIPIENT_NAME="ТОВ «ФЛАГМАН СВ»"
INVOICE_IBAN=UA000000000000000000000000000
INVOICE_EDRPOU=37490783
INVOICE_BANK_NAME=
INVOICE_MFO=
MONOBANK_CORPORATE_TOKEN=
MONOBANK_ACCOUNT_ID=
```

---

## КРОК 8 — Сервіс `MonobankCorporateService` (заглушка)

Файл: `app/Services/MonobankCorporateService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonobankCorporateService
{
    private ?string $token;
    private ?string $accountId;

    public function __construct()
    {
        $this->token     = config('services.monobank_corporate.token');
        $this->accountId = config('services.monobank_corporate.account_id');
    }

    /**
     * Отримати виписку за рахунком.
     * 
     * STUB: поки токен не отримано — повертає порожній масив.
     * Коли буде токен:
     *   GET https://api.monobank.ua/bank/statement/{accountId}/{from}/{to}
     *   Header: X-Token: {token}
     * 
     * @return array<int, array{id: string, description: string, amount: int, time: int}>
     */
    public function getStatements(int $from, int $to): array
    {
        if (empty($this->token) || empty($this->accountId)) {
            Log::debug('MonobankCorporateService: токен не налаштовано, повертаємо порожній масив (STUB)');
            return [];
        }

        try {
            $response = Http::withHeader('X-Token', $this->token)
                ->timeout(10)
                ->get("https://api.monobank.ua/bank/statement/{$this->accountId}/{$from}/{$to}");

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('MonobankCorporateService: помилка відповіді', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonobankCorporateService: виняток', ['message' => $e->getMessage()]);
        }

        return [];
    }
}
```

Додай до `config/services.php`:

```php
'monobank_corporate' => [
    'token'      => env('MONOBANK_CORPORATE_TOKEN'),
    'account_id' => env('MONOBANK_ACCOUNT_ID'),
],
```

---

## КРОК 9 — Сервіс `InvoiceMatcherService`

Файл: `app/Services/InvoiceMatcherService.php`

```php
<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class InvoiceMatcherService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Матчинг транзакцій з pending-рахунками.
     * Шукає номер INV-XXXX-XXXXX у полі description транзакції.
     *
     * @param array<int, array{id: string, description: string, amount: int}> $statements
     */
    public function match(array $statements): void
    {
        if (empty($statements)) {
            return;
        }

        $pendingInvoices = Invoice::where('status', InvoiceStatus::Pending)
            ->whereNull('monobank_statement_id')
            ->get()
            ->keyBy('invoice_number');

        foreach ($statements as $statement) {
            $description = $statement['description'] ?? '';
            $statementId = $statement['id'] ?? null;

            // Шукаємо INV-2026-XXXXX у призначенні платежу
            if (!preg_match('/(INV-\d{4}-\d{5})/', $description, $matches)) {
                continue;
            }

            $invoiceNumber = $matches[1];

            if (!isset($pendingInvoices[$invoiceNumber])) {
                continue;
            }

            /** @var Invoice $invoice */
            $invoice = $pendingInvoices[$invoiceNumber];

            // Перевіряємо суму (amount у Monobank — в копійках, може бути від'ємним для дебету)
            $paidAmount = abs($statement['amount'] ?? 0);
            if ($paidAmount !== $invoice->amount) {
                Log::warning('InvoiceMatcher: сума не збігається', [
                    'invoice'      => $invoiceNumber,
                    'expected'     => $invoice->amount,
                    'got'          => $paidAmount,
                    'statement_id' => $statementId,
                ]);
                // Не відхиляємо — адмін вирішить вручну через Filament
                continue;
            }

            $this->invoiceService->markAsPaid($invoice, $statementId);

            Log::info('InvoiceMatcher: рахунок підтверджено автоматично', [
                'invoice_number' => $invoiceNumber,
                'statement_id'   => $statementId,
            ]);
        }
    }
}
```

---

## КРОК 10 — Команда `CheckInvoicePayments`

```bash
php artisan make:command CheckInvoicePayments
```

Файл: `app/Console/Commands/CheckInvoicePayments.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\InvoiceMatcherService;
use App\Services\MonobankCorporateService;
use Illuminate\Console\Command;

class CheckInvoicePayments extends Command
{
    protected $signature   = 'invoices:check-payments';
    protected $description = 'Перевірити нові надходження по IBAN та підтвердити рахунки';

    public function handle(
        MonobankCorporateService $monobank,
        InvoiceMatcherService $matcher
    ): int {
        $to   = now()->timestamp;
        $from = now()->subMinutes(10)->timestamp; // остання перевірка + буфер

        $statements = $monobank->getStatements($from, $to);
        $matcher->match($statements);

        $this->info('Перевірку завершено. Транзакцій отримано: ' . count($statements));

        return self::SUCCESS;
    }
}
```

Зареєструй в `routes/console.php`:

```php
Schedule::command('invoices:check-payments')->everyFiveMinutes();
```

---

## КРОК 11 — Event `InvoicePaid`

```bash
php artisan make:event InvoicePaid
```

Файл: `app/Events/InvoicePaid.php`

```php
<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice
    ) {}
}
```

Створи listener-заглушку `app/Listeners/ActivateOrderOnInvoicePaid.php`:

```php
<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use Illuminate\Support\Facades\Log;

class ActivateOrderOnInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->order_id) {
            // TODO: активувати order (залежить від логіки Order::activate())
            Log::info('InvoicePaid: потрібно активувати order', [
                'invoice_id' => $invoice->id,
                'order_id'   => $invoice->order_id,
            ]);
        }
    }
}
```

Зареєструй у `app/Providers/EventServiceProvider.php`:

```php
\App\Events\InvoicePaid::class => [
    \App\Listeners\ActivateOrderOnInvoicePaid::class,
],
```

---

## КРОК 12 — PDF шаблон

Файл: `resources/views/pdf/invoice.blade.php`

Повний A4-шаблон у стилі офіційного рахунку-фактури. Використовує inline CSS (dompdf не підтримує зовнішні стилі).

```html
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    .page { padding: 30px 40px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
    .logo { font-size: 20px; font-weight: 700; color: #2563eb; }
    .invoice-meta { text-align: right; }
    .invoice-meta .number { font-size: 16px; font-weight: 700; }
    .invoice-meta .date { color: #6b7280; margin-top: 4px; }
    .section { margin-bottom: 20px; }
    .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px; }
    .parties { display: flex; gap: 40px; margin-bottom: 24px; }
    .party { flex: 1; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
    .party-name { font-weight: 700; font-size: 12px; margin-bottom: 4px; }
    .party-detail { color: #374151; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table thead tr { background: #2563eb; color: #fff; }
    table thead th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; }
    table tbody tr:nth-child(even) { background: #f9fafb; }
    table tbody td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .total-row { background: #eff6ff !important; font-weight: 700; }
    .total-row td { border-top: 2px solid #2563eb; font-size: 13px; }
    .payment-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 16px; margin-bottom: 20px; }
    .payment-box-title { font-weight: 700; color: #166534; margin-bottom: 10px; }
    .payment-row { display: flex; margin-bottom: 5px; }
    .payment-label { color: #6b7280; width: 160px; flex-shrink: 0; }
    .payment-value { font-weight: 600; word-break: break-all; }
    .purpose-box { background: #fefce8; border: 1px solid #fde047; border-radius: 6px; padding: 12px; margin-bottom: 20px; }
    .purpose-text { font-weight: 700; font-size: 12px; }
    .footer { border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 9px; text-align: center; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-paid    { background: #dcfce7; color: #166534; }
</style>
</head>
<body>
<div class="page">
    <!-- Шапка -->
    <div class="header">
        <div>
            <div class="logo">My Job</div>
            <div style="color:#6b7280; margin-top:4px;">myjob.co.ua</div>
        </div>
        <div class="invoice-meta">
            <div class="number">Рахунок № {{ $invoice->invoice_number }}</div>
            <div class="date">від {{ $invoice->created_at->format('d.m.Y') }}</div>
            <div style="margin-top:6px;">
                <span class="status-badge {{ $invoice->isPaid() ? 'status-paid' : 'status-pending' }}">
                    {{ $invoice->status->label() }}
                </span>
            </div>
        </div>
    </div>

    <!-- Постачальник та Покупець -->
    <div class="parties">
        <div class="party">
            <div class="section-title">Постачальник</div>
            <div class="party-name">{{ $invoice->recipient_name }}</div>
            <div class="party-detail">ЄДРПОУ: {{ $invoice->edrpou }}</div>
            <div class="party-detail">{{ $invoice->bank_name }}</div>
            @if($invoice->mfo)
            <div class="party-detail">МФО: {{ $invoice->mfo }}</div>
            @endif
            <div class="party-detail" style="word-break:break-all;">IBAN: {{ $invoice->iban }}</div>
        </div>
        <div class="party">
            <div class="section-title">Покупець</div>
            <div class="party-name">{{ $invoice->payer_name ?? '—' }}</div>
            @if($invoice->payer_edrpou)
            <div class="party-detail">ЄДРПОУ / ІПН: {{ $invoice->payer_edrpou }}</div>
            @endif
        </div>
    </div>

    <!-- Таблиця послуг -->
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Найменування послуги</th>
                <th style="text-align:right;">Сума (грн)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Послуги платформи My Job (рекламне розміщення)</td>
                <td style="text-align:right;">{{ $invoice->amountFormatted() }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" style="text-align:right; padding-right:20px;">Разом до сплати:</td>
                <td style="text-align:right;">{{ $invoice->amountFormatted() }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Реквізити для оплати -->
    <div class="payment-box">
        <div class="payment-box-title">Реквізити для оплати</div>
        <div class="payment-row">
            <div class="payment-label">Отримувач:</div>
            <div class="payment-value">{{ $invoice->recipient_name }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">ЄДРПОУ:</div>
            <div class="payment-value">{{ $invoice->edrpou }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">IBAN:</div>
            <div class="payment-value">{{ $invoice->iban }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">Банк:</div>
            <div class="payment-value">{{ $invoice->bank_name }}</div>
        </div>
        @if($invoice->mfo)
        <div class="payment-row">
            <div class="payment-label">МФО:</div>
            <div class="payment-value">{{ $invoice->mfo }}</div>
        </div>
        @endif
    </div>

    <!-- Призначення платежу -->
    <div class="purpose-box">
        <div class="section-title">Призначення платежу (вказати точно)</div>
        <div class="purpose-text">{{ $invoice->payment_purpose }}</div>
    </div>

    <!-- Дійсний до -->
    @if($invoice->expires_at)
    <div style="color:#6b7280; margin-bottom:20px; font-size:10px;">
        ⚠ Рахунок дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
    </div>
    @endif

    <div class="footer">
        Цей документ сформовано автоматично на платформі My Job (myjob.co.ua).
        Підпис та печатка не потрібні. {{ $invoice->recipient_name }} · ЄДРПОУ {{ $invoice->edrpou }}
    </div>
</div>
</body>
</html>
```

---

## КРОК 13 — Volt компонент `billing-invoice`

```bash
php artisan make:volt billing/invoice --class
```

Файл: `resources/views/livewire/billing/invoice.blade.php`

```php
<?php

use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Volt\Component;

new class extends Component {

    public Invoice $invoice;

    public function mount(string $invoiceNumber): void
    {
        $this->invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    public function downloadPdf(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdf = app(InvoiceService::class)->generatePdf($this->invoice);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "{$this->invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}; ?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <!-- Статус -->
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">
            Рахунок {{ $invoice->invoice_number }}
        </h1>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
            {{ $invoice->status->color() === 'green' ? 'bg-green-100 text-green-800' : '' }}
            {{ $invoice->status->color() === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
            {{ $invoice->status->color() === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}
            {{ $invoice->status->color() === 'red' ? 'bg-red-100 text-red-800' : '' }}
        ">
            {{ $invoice->status->label() }}
        </span>
    </div>

    @if($invoice->isPaid())
        <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <div>
                <p class="font-semibold text-green-800">Оплату підтверджено</p>
                <p class="text-sm text-green-600">{{ $invoice->paid_at?->format('d.m.Y о H:i') }}</p>
            </div>
        </div>
    @else
        <!-- Сума -->
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
            <div class="text-sm text-blue-600 mb-1">До сплати</div>
            <div class="text-3xl font-bold text-blue-900">{{ $invoice->amountFormatted() }}</div>
            @if($invoice->expires_at)
                <div class="text-sm text-blue-500 mt-1">
                    Дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
                </div>
            @endif
        </div>

        <!-- Реквізити -->
        <div class="mb-6 bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
            <div class="p-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Реквізити для оплати
                </h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Отримувач</span>
                        <span class="font-medium text-right">{{ $invoice->recipient_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">ЄДРПОУ</span>
                        <span class="font-medium">{{ $invoice->edrpou }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Банк</span>
                        <span class="font-medium text-right">{{ $invoice->bank_name }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-gray-500">IBAN</span>
                        <span class="font-mono font-medium text-xs break-all">{{ $invoice->iban }}</span>
                    </div>
                </div>
            </div>

            <!-- Призначення платежу -->
            <div class="p-4">
                <div class="text-sm text-gray-500 mb-1">Призначення платежу</div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="font-semibold text-yellow-900 text-sm">{{ $invoice->payment_purpose }}</p>
                    <p class="text-xs text-yellow-600 mt-1">
                        ⚠ Вкажіть точно це призначення при переказі — інакше оплату не буде підтверджено автоматично
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Кнопка PDF -->
    <div class="flex gap-3">
        <button
            wire:click="downloadPdf"
            class="flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-medium hover:bg-gray-700 transition-colors"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Завантажити PDF
        </button>
    </div>
</div>
```

---

## КРОК 14 — Маршрути

У `routes/web.php` в групі middleware `['auth', 'role:employer']`:

```php
Route::get('/billing/invoice/{invoiceNumber}', function (string $invoiceNumber) {
    return view('livewire.billing.invoice', ['invoiceNumber' => $invoiceNumber]);
})->name('employer.billing.invoice.show');
```

---

## КРОК 15 — Filament Resource `InvoiceResource`

```bash
php artisan make:filament-resource Invoice --generate
```

Файл: `app/Filament/Resources/InvoiceResource.php`

Налаштуй таблицю:

```php
Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable(),
Tables\Columns\TextColumn::make('user.name')->label('Роботодавець'),
Tables\Columns\TextColumn::make('payer_name')->label('Платник'),
Tables\Columns\TextColumn::make('amount')
    ->label('Сума')
    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' грн'),
Tables\Columns\BadgeColumn::make('status')
    ->label('Статус')
    ->formatStateUsing(fn ($state) => $state->label())
    ->colors([
        'warning' => 'pending',
        'success' => 'paid',
        'danger'  => fn ($state) => in_array($state->value, ['expired', 'cancelled']),
    ]),
Tables\Columns\TextColumn::make('created_at')->label('Створено')->dateTime('d.m.Y H:i'),
Tables\Columns\TextColumn::make('paid_at')->label('Оплачено')->dateTime('d.m.Y H:i')->placeholder('—'),
```

Додай action «Підтвердити вручну»:

```php
Tables\Actions\Action::make('confirm_payment')
    ->label('Підтвердити оплату')
    ->icon('heroicon-o-check-circle')
    ->color('success')
    ->visible(fn (Invoice $record) => $record->status === \App\Enums\InvoiceStatus::Pending)
    ->requiresConfirmation()
    ->action(function (Invoice $record) {
        app(\App\Services\InvoiceService::class)->markAsPaid($record, 'manual');
        \Filament\Notifications\Notification::make()
            ->title('Оплату підтверджено')
            ->success()
            ->send();
    }),
```

---

## КРОК 16 — Тести

Файл: `tests/Feature/Employer/InvoiceTest.php`

```php
<?php

namespace Tests\Feature\Employer;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceMatcherService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_is_created_with_correct_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employer]);

        $invoice = app(InvoiceService::class)->create($user, 50000);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'amount'  => 50000,
            'status'  => InvoiceStatus::Pending->value,
            'edrpou'  => config('invoice.edrpou'),
        ]);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertStringContainsString($invoice->invoice_number, $invoice->payment_purpose);
    }

    #[Test]
    public function invoice_numbers_are_unique_and_sequential(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employer]);
        $service = app(InvoiceService::class);

        $inv1 = $service->create($user, 10000);
        $inv2 = $service->create($user, 20000);

        $this->assertNotEquals($inv1->invoice_number, $inv2->invoice_number);
    }

    #[Test]
    public function mark_as_paid_fires_event_and_sets_status(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        app(InvoiceService::class)->markAsPaid($invoice, 'test-statement-id');

        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
        Event::assertDispatched(InvoicePaid::class);
    }

    #[Test]
    public function matcher_finds_invoice_by_payment_purpose(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        $statements = [
            [
                'id'          => 'mono-stmt-001',
                'description' => "Оплата послуг My Job, рахунок {$invoice->invoice_number}",
                'amount'      => 50000,
            ],
        ];

        app(InvoiceMatcherService::class)->match($statements);

        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        Event::assertDispatched(InvoicePaid::class);
    }

    #[Test]
    public function matcher_skips_wrong_amount(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        $statements = [
            [
                'id'          => 'mono-stmt-002',
                'description' => "Рахунок {$invoice->invoice_number}",
                'amount'      => 10000, // неправильна сума
            ],
        ];

        app(InvoiceMatcherService::class)->match($statements);

        $this->assertEquals(InvoiceStatus::Pending, $invoice->fresh()->status);
        Event::assertNotDispatched(InvoicePaid::class);
    }

    #[Test]
    public function invoice_page_renders_for_owner(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Pending,
        ]);

        $this->actingAs($user);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    #[Test]
    public function invoice_page_forbidden_for_another_user(): void
    {
        $owner  = User::factory()->create(['role' => UserRole::Employer]);
        $other  = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertNotFound();
    }

    #[Test]
    public function monobank_stub_returns_empty_array_without_token(): void
    {
        config(['services.monobank_corporate.token' => null]);

        $statements = app(\App\Services\MonobankCorporateService::class)
            ->getStatements(now()->subHour()->timestamp, now()->timestamp);

        $this->assertIsArray($statements);
        $this->assertEmpty($statements);
    }
}
```

Також додай `InvoiceFactory`:

```bash
php artisan make:factory InvoiceFactory --model=Invoice
```

```php
public function definition(): array
{
    return [
        'user_id'         => \App\Models\User::factory(),
        'invoice_number'  => 'INV-' . now()->year . '-' . str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
        'amount'          => fake()->numberBetween(10000, 500000),
        'status'          => \App\Enums\InvoiceStatus::Pending,
        'recipient_name'  => 'ТОВ «ФЛАГМАН СВ»',
        'iban'            => 'UA000000000000000000000000000',
        'edrpou'          => '37490783',
        'bank_name'       => 'АТ «УНІВЕРСАЛ БАНК»',
        'payment_purpose' => fn (array $attrs) => "Оплата послуг My Job, рахунок {$attrs['invoice_number']}",
        'expires_at'      => now()->addDays(30),
    ];
}
```

---

## КРОК 17 — Запуск тестів

```bash
php artisan test tests/Feature/Employer/InvoiceTest.php --stop-on-failure
```

Очікувані результати: **8/8 PASS**.

---

## Важливі обмеження

1. **Не чіпати** існуючі файли `CheckoutService`, `billing-checkout-addon`, `AddonType` — тільки розширення
2. **Volt-only** — жодних стандартних Livewire компонентів
3. **PHPUnit 12** з `#[Test]` атрибутами — без `/** @test */`
4. **`actingAs()`** викликати до `Volt::test()`, не в ланцюжку
5. **`config('invoice.*')`** — всі реквізити тільки з конфігу, не хардкодити

---

## Що активується після отримання Monobank Corporate токена

Замінити в `MonobankCorporateService::getStatements()` заглушку на реальний HTTP запит:

```php
// Було (stub):
return [];

// Стане:
$response = Http::withHeader('X-Token', $this->token)
    ->get("https://api.monobank.ua/bank/statement/{$this->accountId}/{$from}/{$to}");
return $response->json() ?? [];
```

Більше нічого міняти не потрібно — решта архітектури вже готова.
