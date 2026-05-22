# Claude Code Prompt: Інтеграція оплати через IBAN (Monobank API)

## Контекст проекту

Проект: **My Job** — платформа пошуку роботи на Laravel 13, Livewire 3 (Volt), Filament, PostgreSQL.
Оплата: IBAN через Monobank API (без платіжного шлюзу, вручну через банківський переказ з верифікацією).

---

## Завдання

Реалізуй повний цикл оплати через IBAN (Monobank API): від формування рахунку та QR-коду до автоматичної верифікації надходження через Webhook і резервний Cron-polling.

---

## Крок 1 — Конфігурація та міграція

### 1.1 `.env` та `config/services.php`

Додай у `.env`:
```env
MONO_TOKEN=your_token_here
MONO_ACCOUNT_ID=your_account_id_here
MONO_WEBHOOK_SECRET=your_webhook_secret_here
```

Додай у `config/services.php`:
```php
'monobank' => [
    'token'      => env('MONO_TOKEN'),
    'account_id' => env('MONO_ACCOUNT_ID'),
    'webhook_secret' => env('MONO_WEBHOOK_SECRET'),
],
```

### 1.2 Міграція

Якщо таблиця `orders` ще не існує — створи її. Якщо існує — додай колонки через окрему міграцію:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('payment_id')->unique()->nullable();       // ID транзакції від банку (statement_id)
    $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])->default('pending');
    $table->string('payment_purpose')->nullable();            // "Оплата замовлення №ORDER-XXXX"
    $table->decimal('amount', 10, 2);                        // сума в гривнях
    $table->timestamp('paid_at')->nullable();
});
```

> Якщо `status` або `payment_id` вже є — адаптуй міграцію, не дублюй колонки.

---

## Крок 2 — Сервіс MonobankService

Створи `app/Services/MonobankService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonobankService
{
    protected string $baseUrl = 'https://api.monobank.ua';
    protected string $token;
    protected string $accountId;

    public function __construct()
    {
        $this->token     = config('services.monobank.token');
        $this->accountId = config('services.monobank.account_id');
    }

    /**
     * Отримати виписку за останні N секунд.
     * Monobank повертає суми в копійках.
     */
    public function getStatements(int $fromTimestamp, int $toTimestamp): array
    {
        $response = Http::withHeaders(['X-Token' => $this->token])
            ->get("{$this->baseUrl}/personal/statement/{$this->accountId}/{$fromTimestamp}/{$toTimestamp}");

        if ($response->failed()) {
            Log::error('Monobank statement error', ['body' => $response->body()]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Зареєструвати Webhook (викликати один раз через Artisan або Tinker).
     */
    public function registerWebhook(string $url): bool
    {
        $response = Http::withHeaders(['X-Token' => $this->token])
            ->post("{$this->baseUrl}/personal/webhook", ['webHookUrl' => $url]);

        return $response->successful();
    }
}
```

---

## Крок 3 — Модель Order та генерація рахунку

### 3.1 Модель `app/Models/Order.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'payment_id',
        'status',
        'payment_purpose',
        'amount',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount'  => 'decimal:2',
    ];

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
```

### 3.2 Генерація призначення платежу

Формат: `Оплата замовлення №ORDER-{id}` — унікальний рядок для пошуку в description виписки.

```php
// При створенні замовлення (наприклад, у PaymentController або Volt-компоненті):
$order = Order::create([
    'user_id'         => auth()->id(),
    'plan_id'         => $planId,
    'status'          => 'pending',
    'amount'          => $amount,
    'payment_purpose' => 'Оплата замовлення №ORDER-' . $orderId, // заповнюється після створення
]);

$order->update([
    'payment_purpose' => 'Оплата замовлення №ORDER-' . $order->id,
]);
```

---

## Крок 4 — QR-код (EPC Standard)

### 4.1 Встановлення пакету

```bash
composer require bacon/bacon-qr-code
```

### 4.2 Хелпер `app/Support/QrCodeGenerator.php`

```php
<?php

namespace App\Support;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    /**
     * Генерує EPC QR-код для банківського переказу.
     * Специфікація: https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/quick-response-code-guidelines-enable-data-capture-initiation
     */
    public static function epc(
        string $recipientName,
        string $iban,
        float  $amount,
        string $purpose,
        string $edrpou = ''
    ): string {
        // EPC payload (рядки фіксованого порядку)
        $lines = [
            'BCD',           // Service Tag
            '002',           // Version
            '1',             // Character set (UTF-8)
            'SCT',           // Identification (SEPA Credit Transfer)
            '',              // BIC (необов'язково)
            $recipientName,
            $iban,
            'UAH' . number_format($amount, 2, '.', ''),
            '',              // Purpose code
            $purpose,        // Remittance information (unstructured)
            $edrpou,         // Beneficiary to originator info
        ];

        $payload = implode("\n", $lines);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        return $writer->writeString($payload);
    }
}
```

### 4.3 Відображення в Blade / Volt-компоненті

```blade
{!! \App\Support\QrCodeGenerator::epc(
    recipientName: 'ТОВ МАЙ ДЖОБs',
    iban: config('app.company_iban'),   // додай у config/app.php
    amount: $order->amount,
    purpose: $order->payment_purpose
) !!}
```

Додай у `config/app.php`:
```php
'company_iban'  => env('COMPANY_IBAN', 'UA000000000000000000000000000'),
'company_edrpou' => env('COMPANY_EDRPOU', ''),
```

---

## Крок 5 — Webhook-ендпоінт

### 5.1 Маршрут (без CSRF)

У `routes/api.php`:
```php
Route::post('/mono/webhook', \App\Http\Controllers\Api\MonobankWebhookController::class)
    ->name('mono.webhook');
```

Переконайся, що маршрут виключений з CSRF у `bootstrap/app.php` або `App\Http\Middleware\VerifyCsrfToken`:
```php
protected $except = [
    'api/*',
];
```

### 5.2 Контролер `app/Http/Controllers/Api/MonobankWebhookController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMonobankPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonobankWebhookController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        // Базова верифікація: перевіряємо наявність токена в заголовках
        // Monobank не підписує Webhook HMAC — перевіряємо IP або секретний рядок в URL
        $data = $request->json()->all();

        if (empty($data['data']['statementItem'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $statement = $data['data']['statementItem'];

        Log::info('Monobank webhook received', ['statement_id' => $statement['id'] ?? null]);

        // Обробку виносимо в Job (щоб Webhook отримав відповідь 200 якнайшвидше)
        ProcessMonobankPayment::dispatch($statement);

        return response()->json(['status' => 'ok'], 200);
    }
}
```

---

## Крок 6 — Job для обробки платежу

Створи `app/Jobs/ProcessMonobankPayment.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMonobankPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $statement) {}

    public function handle(): void
    {
        $statementId = $this->statement['id']     ?? null;
        $description = $this->statement['description'] ?? '';
        $amountKopeks = $this->statement['amount'] ?? 0;

        // Беремо тільки надходження (amount > 0)
        if ($amountKopeks <= 0) {
            return;
        }

        // Конвертація копійок → гривні
        $amountUah = $amountKopeks / 100;

        // Пошук номера замовлення в description
        if (!preg_match('/ORDER-(\d+)/i', $description, $matches)) {
            Log::warning('Monobank: order not found in description', ['description' => $description]);
            return;
        }

        $orderId = (int) $matches[1];

        // Захист від дублікатів
        $alreadyProcessed = Order::where('payment_id', $statementId)->exists();
        if ($alreadyProcessed) {
            Log::info('Monobank: duplicate statement ignored', ['statement_id' => $statementId]);
            return;
        }

        $order = Order::where('id', $orderId)
                      ->where('status', 'pending')
                      ->first();

        if (!$order) {
            Log::warning('Monobank: pending order not found', ['order_id' => $orderId]);
            return;
        }

        // Перевірка суми (допустима похибка ±0.01 грн через float)
        if (abs($order->amount - $amountUah) > 0.01) {
            Log::warning('Monobank: amount mismatch', [
                'expected' => $order->amount,
                'received' => $amountUah,
                'order_id' => $orderId,
            ]);
            // Не відхиляємо одразу — можлива неповна оплата, залишаємо pending для ручної перевірки
            return;
        }

        // Активація замовлення
        $order->update([
            'status'     => 'paid',
            'payment_id' => $statementId,
            'paid_at'    => now(),
        ]);

        Log::info('Monobank: order paid', ['order_id' => $orderId, 'amount' => $amountUah]);

        // TODO: dispatch event OrderPaid або activate subscription
        // event(new \App\Events\OrderPaid($order));
    }
}
```

---

## Крок 7 — Резервний Cron-polling

### 7.1 Artisan-команда `app/Console/Commands/CheckMonobankPayments.php`

```php
<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMonobankPayment;
use App\Services\MonobankService;
use Illuminate\Console\Command;

class CheckMonobankPayments extends Command
{
    protected $signature   = 'mono:check-payments';
    protected $description = 'Перевірити виписку Monobank і активувати pending-замовлення';

    public function handle(MonobankService $mono): void
    {
        // Перевіряємо останні 35 хвилин (з запасом відносно 30-хв. крону)
        $from = now()->subMinutes(35)->timestamp;
        $to   = now()->timestamp;

        $statements = $mono->getStatements($from, $to);

        foreach ($statements as $statement) {
            ProcessMonobankPayment::dispatch($statement);
        }

        $this->info('Перевірено ' . count($statements) . ' транзакцій.');
    }
}
```

### 7.2 Розклад у `routes/console.php` (Laravel 11+)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mono:check-payments')->everyThirtyMinutes();
```

---

## Крок 8 — Artisan-команда для реєстрації Webhook (одноразово)

Створи `app/Console/Commands/RegisterMonobankWebhook.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\MonobankService;
use Illuminate\Console\Command;

class RegisterMonobankWebhook extends Command
{
    protected $signature   = 'mono:register-webhook';
    protected $description = 'Зареєструвати Webhook URL у Monobank';

    public function handle(MonobankService $mono): void
    {
        $url = route('mono.webhook'); // повна URL

        if ($mono->registerWebhook($url)) {
            $this->info("Webhook зареєстровано: {$url}");
        } else {
            $this->error('Помилка реєстрації Webhook. Перевір MONO_TOKEN і доступність URL.');
        }
    }
}
```

Запуск після деплою:
```bash
php artisan mono:register-webhook
```

---

## Крок 9 — Тести

Створи `tests/Feature/Payment/MonobankPaymentTest.php`:

```php
<?php

namespace Tests\Feature\Payment;

use App\Jobs\ProcessMonobankPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonobankPaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'data' => [
                'statementItem' => [
                    'id'          => 'stmt_abc123',
                    'amount'      => 50000, // 500 грн у копійках
                    'description' => 'Оплата замовлення №ORDER-1',
                    'time'        => now()->timestamp,
                ],
            ],
        ];

        $this->postJson(route('mono.webhook'), $payload)
             ->assertStatus(200);

        Queue::assertPushed(ProcessMonobankPayment::class);
    }

    #[Test]
    public function job_marks_order_as_paid(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'         => $user->id,
            'status'          => 'pending',
            'amount'          => 500.00,
            'payment_purpose' => 'Оплата замовлення №ORDER-1',
        ]);

        $statement = [
            'id'          => 'stmt_unique_001',
            'amount'      => 50000,
            'description' => 'Оплата замовлення №ORDER-' . $order->id,
            'time'        => now()->timestamp,
        ];

        (new ProcessMonobankPayment($statement))->handle();

        $this->assertDatabaseHas('orders', [
            'id'         => $order->id,
            'status'     => 'paid',
            'payment_id' => 'stmt_unique_001',
        ]);
    }

    #[Test]
    public function duplicate_statement_is_ignored(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'    => $user->id,
            'status'     => 'paid',
            'amount'     => 500.00,
            'payment_id' => 'stmt_dup_001',
        ]);

        $statement = [
            'id'          => 'stmt_dup_001',
            'amount'      => 50000,
            'description' => 'Оплата замовлення №ORDER-' . $order->id,
            'time'        => now()->timestamp,
        ];

        // Запускаємо двічі
        (new ProcessMonobankPayment($statement))->handle();
        (new ProcessMonobankPayment($statement))->handle();

        // Рахуємо: запис повинен залишитись єдиним і незмінним
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function amount_mismatch_does_not_mark_order_paid(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status'  => 'pending',
            'amount'  => 500.00,
        ]);

        $statement = [
            'id'          => 'stmt_wrong_amount',
            'amount'      => 10000, // лише 100 грн замість 500
            'description' => 'Оплата замовлення №ORDER-' . $order->id,
            'time'        => now()->timestamp,
        ];

        (new ProcessMonobankPayment($statement))->handle();

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'pending', // залишається pending
        ]);
    }
}
```

> Для тестів потрібна `OrderFactory`. Якщо не існує — згенеруй:
> ```bash
> php artisan make:factory OrderFactory --model=Order
> ```

---

## Підсумкова структура файлів

```
app/
  Console/Commands/
    CheckMonobankPayments.php
    RegisterMonobankWebhook.php
  Http/Controllers/Api/
    MonobankWebhookController.php
  Jobs/
    ProcessMonobankPayment.php
  Models/
    Order.php
  Services/
    MonobankService.php
  Support/
    QrCodeGenerator.php
config/
  services.php  ← додати 'monobank' блок
routes/
  api.php       ← POST /mono/webhook
  console.php   ← Schedule
database/migrations/
  xxxx_add_payment_fields_to_orders_table.php
tests/Feature/Payment/
  MonobankPaymentTest.php
```

---

## Важливі нотатки

- **Monobank не підписує Webhook HMAC** — для захисту використовуй секретний токен у URL (`/api/mono/webhook?token=xxx`) або обмеж по IP Monobank.
- **Queue must be running**: Job `ProcessMonobankPayment` потребує активного queue worker (`php artisan queue:work`).
- **Monobank rate limit**: `GET /personal/statement` — не частіше ніж раз на хвилину. Cron раз на 30 хвилин — безпечно.
- **Копійки → гривні**: завжди ділити на 100, ніколи не зберігати копійки в `amount`.
- **`$this->actingAs()` перед `Volt::test()`** — якщо будуть Volt-тести для payment flow, дотримуйся цього порядку.
