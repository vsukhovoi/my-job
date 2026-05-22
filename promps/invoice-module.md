# Invoice Module — Laravel (IBAN-оплата + PDF рахунок)

Модуль автоматичного створення рахунків-фактур та генерації PDF.
Оплата через IBAN (банківський переказ), без Stripe/LiqPay.

---

## Залежність

```bash
composer require barryvdh/laravel-dompdf
```

---

## Файли модуля

```
app/Enums/InvoiceStatus.php
app/Models/Invoice.php
app/Services/InvoiceService.php
app/Events/InvoicePaid.php
config/invoice.php
database/migrations/xxxx_create_invoices_table.php
resources/views/pdf/invoice.blade.php
resources/views/livewire/billing/invoice.blade.php   ← Volt-сторінка перегляду
routes/web.php  ← маршрут /billing/invoice/{invoiceNumber}
```

---

## 1. Migration

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
    $table->string('invoice_number')->unique();
    $table->unsignedBigInteger('amount'); // у копійках (грн * 100)
    $table->string('status')->default('pending');

    $table->string('payer_name')->nullable();
    $table->string('payer_edrpou')->nullable();

    $table->string('recipient_name');
    $table->string('iban');
    $table->string('edrpou');
    $table->string('bank_name');
    $table->string('mfo')->nullable();

    $table->string('payment_purpose');

    $table->string('monobank_statement_id')->nullable()->unique();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('expires_at')->nullable();

    $table->timestamps();
});
```

---

## 2. app/Enums/InvoiceStatus.php

```php
<?php
declare(strict_types=1);
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

## 3. app/Models/Invoice.php

```php
<?php
declare(strict_types=1);
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
        'status'     => InvoiceStatus::class,
        'paid_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function amountFormatted(): string
    {
        return number_format($this->amount / 100, 2, '.', ' ') . ' грн';
    }

    // Сума прописом українською (без зовнішніх пакетів)
    public function amountInWords(): string
    {
        $total    = (int) $this->amount;
        $hryvnias = intdiv($total, 100);
        $kopecks  = $total % 100;

        $hryvniaWord = self::pluralUk($hryvnias, 'гривня', 'гривні', 'гривень');
        $kopeckWord  = self::pluralUk($kopecks, 'копійка', 'копійки', 'копійок');

        $words = self::numberToWordsUk($hryvnias, true);
        $words = mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);

        return $words . ' ' . $hryvniaWord . ', '
            . sprintf('%02d', $kopecks) . ' ' . $kopeckWord . '.';
    }

    private static function pluralUk(int $n, string $one, string $few, string $many): string
    {
        $mod10  = $n % 10;
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 19) return $many;
        if ($mod10 === 1) return $one;
        if ($mod10 >= 2 && $mod10 <= 4) return $few;
        return $many;
    }

    private static function numberToWordsUk(int $n, bool $feminine = false): string
    {
        if ($n === 0) return 'нуль';

        $hundreds = ['', 'сто', 'двісті', 'триста', 'чотириста', 'п\'ятсот', 'шістсот', 'сімсот', 'вісімсот', 'дев\'ятсот'];
        $tens     = ['', 'десять', 'двадцять', 'тридцять', 'сорок', 'п\'ятдесят', 'шістдесят', 'сімдесят', 'вісімдесят', 'дев\'яносто'];
        $teens    = ['десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', 'п\'ятнадцять', 'шістнадцять', 'сімнадцять', 'вісімнадцять', 'дев\'ятнадцять'];
        $unitsM   = ['', 'один', 'два', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять'];
        $unitsF   = ['', 'одна', 'дві', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять'];

        $parts = [];

        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $parts[] = self::numberToWordsUk($m, false) . ' ' . self::pluralUk($m, 'мільйон', 'мільйони', 'мільйонів');
            $n %= 1_000_000;
        }
        if ($n >= 1_000) {
            $t = intdiv($n, 1_000);
            $parts[] = self::numberToWordsUk($t, true) . ' ' . self::pluralUk($t, 'тисяча', 'тисячі', 'тисяч');
            $n %= 1_000;
        }
        if ($n >= 100) { $parts[] = $hundreds[intdiv($n, 100)]; $n %= 100; }
        if ($n >= 10 && $n <= 19) { $parts[] = $teens[$n - 10]; $n = 0; }
        elseif ($n >= 20) { $parts[] = $tens[intdiv($n, 10)]; $n %= 10; }
        if ($n > 0) $parts[] = $feminine ? $unitsF[$n] : $unitsM[$n];

        return implode(' ', array_filter($parts));
    }
}
```

---

## 4. config/invoice.php

```php
<?php
return [
    'recipient_name' => env('INVOICE_RECIPIENT_NAME', 'ТОВ «НАЗВА»'),
    'iban'           => env('INVOICE_IBAN', 'UA000000000000000000000000000'),
    'edrpou'         => env('INVOICE_EDRPOU', '00000000'),
    'bank_name'      => env('INVOICE_BANK_NAME', ''),
    'mfo'            => env('INVOICE_MFO', ''),
];
```

`.env` змінні:
```
INVOICE_RECIPIENT_NAME="ТОВ «НАЗВА КОМПАНІЇ»"
INVOICE_IBAN="UA213220010000026207333334444"
INVOICE_EDRPOU="37490783"
INVOICE_BANK_NAME="АТ «УНIВЕРСАЛ БАНК» (monobank)"
INVOICE_MFO="322001"
```

---

## 5. app/Services/InvoiceService.php

```php
<?php
declare(strict_types=1);
namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(
        User $user,
        int $amountKopecks,
        ?int $orderId = null,
        array $payerData = [],
        ?string $planName = null
    ): Invoice {
        return DB::transaction(function () use ($user, $amountKopecks, $orderId, $payerData, $planName) {
            $number  = $this->generateNumber();
            $date    = now()->locale('uk')->isoFormat('D MMMM YYYY');
            $tariff  = $planName ? ", тариф {$planName}" : '';
            $purpose = "Розміщення інформації на веб-сайті {$platformName}{$tariff}, рахунок № {$number} від {$date} р. без ПДВ";

            return Invoice::create([
                'user_id'         => $user->id,
                'order_id'        => $orderId,
                'invoice_number'  => $number,
                'amount'          => $amountKopecks,
                'status'          => InvoiceStatus::Pending,
                'payer_name'      => $payerData['payer_name'] ?? $user->company?->name,
                'payer_edrpou'    => $payerData['payer_edrpou'] ?? null,
                'recipient_name'  => config('invoice.recipient_name'),
                'iban'            => config('invoice.iban'),
                'edrpou'          => config('invoice.edrpou'),
                'bank_name'       => config('invoice.bank_name'),
                'mfo'             => config('invoice.mfo'),
                'payment_purpose' => $purpose,
                'expires_at'      => now()->addDays(30),
            ]);
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
            'status'                => InvoiceStatus::Paid,
            'paid_at'               => now(),
            'monobank_statement_id' => $statementId,
        ]);
        event(new InvoicePaid($invoice));
    }

    public function cancel(Invoice $invoice, User $user): void
    {
        if ($invoice->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Немає доступу.');
        }
        if ($invoice->status !== InvoiceStatus::Pending) {
            throw new \InvalidArgumentException('Скасувати можна лише рахунок зі статусом «Очікує оплати».');
        }
        $invoice->update(['status' => InvoiceStatus::Cancelled]);
    }

    private function generateNumber(): string
    {
        $seq = str_pad((string)((Invoice::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        return "MJ-{$seq}"; // або будь-який інший префікс
    }
}
```

---

## 6. app/Events/InvoicePaid.php

```php
<?php
declare(strict_types=1);
namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoicePaid
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
```

---

## 7. resources/views/pdf/invoice.blade.php

> ⚠️ **dompdf quirks** — обов'язково читати перед редагуванням:
> - Шрифт: тільки `DejaVu Sans` для кирилиці
> - `font-weight`: використовувати `700`, не `600`
> - Горизонтальна лінія: `<div style="height:1px; background-color:#000; font-size:0; line-height:0;">` — `<hr>` та `border-top` не рендеряться
> - `font-weight` на блок: через `<span style="font-weight:700;">`, не через батьківський `div`
> - Числа: `number_format($amount / 100, 2, ',', ' ')` — кома як десятковий роздільник
> - Логотип: `public_path('img/...')` — абсолютний шлях ФС, не URL

```blade
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    .page { padding: 30px 40px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
    .invoice-meta { text-align: right; margin-top: -50px; }
    .invoice-meta .number { font-size: 16px; font-weight: 700; }
    .invoice-meta .date { color: #6b7280; margin-top: 4px; font-size: 13px; }
    .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px; }
    .parties { display: flex; gap: 40px; margin-bottom: 24px; }
    .party { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
    .party-name { font-weight: 700; font-size: 16px; margin-bottom: 2px; color: #000; line-height: 0.9; }
    .party-detail { color: #000; margin-top: 2px; font-size: 16px; line-height: 0.9; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000; font-size: 14px; }
    table thead tr { background: #f3f4f6; color: #111827; }
    table thead th { padding: 8px 10px; text-align: left; font-weight: 700; border: 1px solid #000; }
    table tbody tr:nth-child(even) { background: #f9fafb; }
    table tbody td { padding: 8px 10px; border: 1px solid #000; }
    .total-row { background: #eff6ff !important; font-weight: 700; }
    .total-row td { border: 1px solid #000; font-size: 14px; }
    .footer { border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 9px; text-align: center; }
</style>
</head>
<body>
<div class="page">

    {{-- Шапка --}}
    <div class="header">
        <div>
            <img src="{{ public_path('img/logo/logo.png') }}" alt="Logo" style="height:120px; width:auto; display:block;">
        </div>
        <div class="invoice-meta">
            <div class="number">Рахунок-фактура № {{ $invoice->invoice_number }}</div>
            <div class="date">від {{ $invoice->created_at->locale('uk')->isoFormat('D MMMM YYYY') }} р.</div>
        </div>
    </div>

    {{-- Постачальник / Покупець --}}
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

    {{-- Таблиця послуг --}}
    <table>
        <thead>
            <tr>
                <th style="width:1%; white-space:nowrap;">№</th>
                <th style="width:auto;">Найменування послуги</th>
                <th style="width:1%; white-space:nowrap; text-align:center;">К-сть</th>
                <th style="width:1%; white-space:nowrap; text-align:center;">Од.</th>
                <th style="width:1%; white-space:nowrap; text-align:right;">Ціна (грн)</th>
                <th style="width:1%; white-space:nowrap; text-align:right;">Сума (грн)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $invoice->payment_purpose }}</td>
                <td style="text-align:center;">1</td>
                <td style="text-align:center;">посл.</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="5" style="text-align:right; padding-right:20px;">Разом до сплати:</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Сума прописом --}}
    <div style="font-size:13px; margin-bottom:16px; line-height:1.6;">
        <span style="color:#6b7280;">Всього на суму:</span><br>
        <span style="font-weight:700;">{{ $invoice->amountInWords() }}</span><br>
        <span style="color:#6b7280;">Без ПДВ</span>
    </div>

    {{-- Правовий блок --}}
    @if($invoice->expires_at)
    <div style="color:#6b7280; margin-bottom:12px; font-size:10px;">
        Рахунок дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
    </div>
    <div style="color:#374151; margin-bottom:8px; font-size:10px; line-height:1.5;">
        На підставі статей 634 та 642 Цивільного кодексу України, оплата цього рахунку є повним і безумовним прийняттям (акцептом) умов Договору публічної оферти про надання послуг, розміщеного за посиланням: example.com/offer, та чинних Тарифів Виконавця, розміщених за посиланням: example.com/pricing. Надання послуг за цим рахунком не потребує підписання двосторонніх паперових Актів приймання-передачі наданих послуг.
    </div>
    {{-- Горизонтальна лінія (НЕ <hr> — не рендериться в dompdf) --}}
    <div style="width:100%; height:1px; background-color:#000; font-size:0; line-height:0; margin-bottom:8px;"></div>
    <div style="color:#374151; font-size:10px; line-height:1.5; margin-bottom:20px;">
        <span style="font-weight:700;">Увага! У призначені платежу обов'язково необхідно вказувати (так як зазначено у рахунку): "Назва послуги, тариф (Ваш тариф), рахунок № ХХ-ХХХХХ від дд.мм.рррр. без ПДВ" (де ХХ-ХХХХХ — номер Вашого рахунку).
        В іншому випадку платіж може бути не зараховано.</span>
    </div>
    @endif

    <div class="footer">
        Цей документ сформовано автоматично. Підпис та печатка не потрібні.
        {{ $invoice->recipient_name }} · ЄДРПОУ {{ $invoice->edrpou }}
    </div>
</div>
</body>
</html>
```

---

## 8. Livewire Volt — сторінка рахунку

`resources/views/livewire/billing/invoice.blade.php`

```php
<?php
use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {

    public Invoice $invoice;

    public function mount(string $invoiceNumber): void
    {
        $this->invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    public function cancel(): void
    {
        try {
            app(InvoiceService::class)->cancel($this->invoice, auth()->user());
            $this->invoice->refresh();
            session()->flash('success', 'Рахунок скасовано.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
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
```

---

## 9. Маршрут

```php
// routes/web.php — в межах auth + role:employer middleware
Volt::route('/billing/invoice/{invoiceNumber}', 'billing.invoice')
    ->name('billing.invoice.show');
```

---

## 10. Використання (виклик з іншого сервісу)

```php
// Створити рахунок
$invoice = app(InvoiceService::class)->create(
    user: $user,
    amountKopecks: 99900,       // 999.00 грн
    orderId: $order->id,        // nullable
    payerData: [
        'payer_name'   => $user->company->name,
        'payer_edrpou' => $user->company->edrpou,
    ],
    planName: 'Старт',          // nullable
);

// Позначити як оплачений
app(InvoiceService::class)->markAsPaid($invoice, statementId: 'mono_abc123');

// Завантажити PDF
$pdf = app(InvoiceService::class)->generatePdf($invoice);
$pdf->download('invoice.pdf');   // або ->stream() для перегляду
```
