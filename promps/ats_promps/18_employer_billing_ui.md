# МОДУЛЬ 18. Кабінет роботодавця — фінанси (вибір тарифу, оплата, історія)

## 🎯 Мета модуля
Роботодавець у своєму кабінеті повинен:
1. Бачити сторінку вибору тарифу для продовження вакансії (15/30/90 днів з ціною)
2. Натиснути «Оплатити» → перейти на сторінку провайдера
3. Після оплати побачити підтвердження
4. У будь-який момент переглянути **свою** історію платежів

**Передумова:** модулі 2, 7, 11A–14 виконано. `CheckoutService` існує. `payment_processed_events` має записи.

---

## 🔍 КРОК 18.1. Розвідка

```bash
# 1. Структура кабінету роботодавця
find resources/views/employer -type f | sort
find app/Http/Controllers/Employer -type f 2>/dev/null | sort
find app/Livewire/Employer -type f 2>/dev/null | sort

# 2. Роути кабінету
php artisan route:list | grep employer

# 3. Middleware для авторизації роботодавця
grep -r "employer\|Employer" app/Http/Middleware/ 2>/dev/null | head -10

# 4. Чи є вже сторінка вакансії з кнопкою «Продовжити»?
# (з модуля 7 — VacancyCountdown компонент)
find resources/views/employer -name "show*" 2>/dev/null
```

Відзвітуй, особливо про структуру роутів. Далі без мого OK не пиши.

---

## 📐 Архітектура сторінок

```
/employer/vacancies/{vacancy}/extend          ← вибір тарифу (GET)
/employer/vacancies/{vacancy}/extend          ← ініціювання оплати (POST)
/employer/vacancies/{vacancy}/payment/success ← після оплати (GET)
/employer/vacancies/{vacancy}/payment/cancel  ← скасування (GET)
/employer/billing                             ← історія всіх платежів (GET)
```

---

## 📂 КРОК 18.2. Маршрути

`routes/employer.php` (або `routes/web.php` — залежно від структури проєкту):

```php
use App\Http\Controllers\Employer\VacancyExtendController;
use App\Http\Controllers\Employer\BillingController;

Route::middleware(['auth', 'verified', 'employer'])->prefix('employer')->name('employer.')->group(function () {

    // --- Вакансії (вже є) ---
    // Route::resource('vacancies', VacancyController::class);

    // --- Продовження вакансії ---
    Route::get('vacancies/{vacancy}/extend',          [VacancyExtendController::class, 'show'])
        ->name('vacancies.extend');
    Route::post('vacancies/{vacancy}/extend',         [VacancyExtendController::class, 'initiate'])
        ->name('vacancies.extend.initiate');
    Route::get('vacancies/{vacancy}/payment/success', [VacancyExtendController::class, 'success'])
        ->name('vacancies.payment.success');
    Route::get('vacancies/{vacancy}/payment/cancel',  [VacancyExtendController::class, 'cancel'])
        ->name('vacancies.payment.cancel');

    // --- Фінанси ---
    Route::get('billing', [BillingController::class, 'index'])->name('billing');
});
```

---

## 📂 КРОК 18.3. `VacancyExtendController`

`app/Http/Controllers/Employer/VacancyExtendController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use App\Payments\CheckoutService;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class VacancyExtendController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * GET /employer/vacancies/{vacancy}/extend
     * Сторінка вибору тарифу.
     */
    public function show(Vacancy $vacancy)
    {
        Gate::authorize('extend', $vacancy); // VacancyPolicy

        abort_if(
            $vacancy->status->value === 'archived',
            403,
            'Архівовану вакансію не можна продовжити.'
        );

        $plans = $this->getPlans($vacancy);

        return view('employer.vacancies.extend', compact('vacancy', 'plans'));
    }

    /**
     * POST /employer/vacancies/{vacancy}/extend
     * Ініціювання оплати — редірект на провайдера.
     */
    public function initiate(Request $request, Vacancy $vacancy)
    {
        Gate::authorize('extend', $vacancy);

        $request->validate([
            'days' => ['required', 'integer', 'in:15,30,90'],
        ]);

        $days = (int) $request->integer('days');

        try {
            $checkoutUrl = $this->checkout->createVacancyExtensionCheckout($vacancy, $days);
        } catch (PaymentGatewayException $e) {
            Log::channel('payments')->error('Employer checkout failed', [
                'vacancy_id' => $vacancy->id,
                'days'       => $days,
                'error'      => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Не вдалося ініціювати оплату. Спробуйте ще раз або зверніться до підтримки.');
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * GET /employer/vacancies/{vacancy}/payment/success
     * Сторінка після успішної оплати.
     *
     * ВАЖЛИВО: webhook приходить ОКРЕМО і з затримкою.
     * Ця сторінка НЕ може гарантувати що вакансію вже продовжено.
     * Показуємо «оплату прийнято, обробляємо...» + Livewire polling.
     */
    public function success(Vacancy $vacancy)
    {
        Gate::authorize('view', $vacancy);

        return view('employer.vacancies.payment-success', compact('vacancy'));
    }

    /**
     * GET /employer/vacancies/{vacancy}/payment/cancel
     * Повернення після скасування оплати.
     */
    public function cancel(Vacancy $vacancy)
    {
        Gate::authorize('view', $vacancy);

        return redirect()
            ->route('employer.vacancies.extend', $vacancy)
            ->with('warning', 'Оплату скасовано. Виберіть тариф та спробуйте ще раз.');
    }

    // =========================================================================

    /**
     * Будує масив тарифів з цінами і description.
     */
    private function getPlans(Vacancy $vacancy): array
    {
        $prices = config('payments.prices', []);

        $plans = [
            15 => [
                'days'        => 15,
                'label'       => '15 днів',
                'price_uah'   => ($prices[15] ?? 0) / 100,
                'description' => 'Підходить для термінових позицій',
                'highlight'   => false,
            ],
            30 => [
                'days'        => 30,
                'label'       => '30 днів',
                'price_uah'   => ($prices[30] ?? 0) / 100,
                'description' => 'Найпопулярніший вибір',
                'highlight'   => true,   // виділений як рекомендований
            ],
            90 => [
                'days'        => 90,
                'label'       => '90 днів',
                'price_uah'   => ($prices[90] ?? 0) / 100,
                'description' => 'Найвигідніша ціна за день',
                'highlight'   => false,
            ],
        ];

        // Додаємо нову дату expires_at для кожного тарифу — щоб показати «до коли»
        foreach ($plans as &$plan) {
            $base = $vacancy->status->value === 'expired'
                ? now()
                : ($vacancy->expires_at ?? now());

            $plan['new_expires_at'] = $base->copy()->addDays($plan['days']);
        }

        return $plans;
    }
}
```

---

## 📂 КРОК 18.4. `BillingController`

`app/Http/Controllers/Employer/BillingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use App\Payments\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /employer/billing
     * Персональна історія платежів роботодавця.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Отримуємо ID всіх вакансій цього роботодавця
        $vacancyIds = Vacancy::query()
            ->where('employer_id', $user->employer?->id ?? $user->id)
            ->pluck('id')
            ->toArray();

        if (empty($vacancyIds)) {
            return view('employer.billing', [
                'transactions' => collect(),
                'stats'        => ['total_count' => 0, 'total_uah' => 0],
            ]);
        }

        // Будуємо LIKE-паттерни для кожного vacancy_id
        // order_id формату: vac_{id}_{days}_{suffix}
        $query = DB::table('payment_processed_events');

        $query->where(function ($q) use ($vacancyIds) {
            foreach ($vacancyIds as $vid) {
                $q->orWhere('order_id', 'LIKE', "vac_{$vid}_%");
            }
        });

        $rawTransactions = $query
            ->orderByDesc('processed_at')
            ->paginate(20);

        // Збагачуємо дані: декодуємо orderId, підтягуємо назви вакансій
        $vacancyTitles = Vacancy::whereIn('id', $vacancyIds)
            ->pluck('title', 'id');

        $transactions = collect($rawTransactions->items())->map(function ($row) use ($vacancyTitles) {
            [$vacancyId, $days] = CheckoutService::parseOrderId($row->order_id);
            $amountUah = $days ? (config("payments.prices.{$days}") ?? 0) / 100 : 0;

            return (object) [
                'event_id'     => $row->event_id,
                'gateway'      => $row->gateway,
                'gateway_label' => match ($row->gateway) {
                    'mono'      => 'MonoPay',
                    'wayforpay' => 'WayForPay',
                    'liqpay'    => 'LiqPay',
                    'stripe'    => 'Stripe',
                    default     => ucfirst($row->gateway),
                },
                'order_id'      => $row->order_id,
                'vacancy_id'    => $vacancyId,
                'vacancy_title' => $vacancyId ? ($vacancyTitles[$vacancyId] ?? "Вакансія #{$vacancyId}") : '—',
                'days'          => $days,
                'amount_uah'    => $amountUah,
                'processed_at'  => \Carbon\Carbon::parse($row->processed_at),
            ];
        });

        // Загальна статистика роботодавця (за весь час)
        $allTransactions = DB::table('payment_processed_events')
            ->where(function ($q) use ($vacancyIds) {
                foreach ($vacancyIds as $vid) {
                    $q->orWhere('order_id', 'LIKE', "vac_{$vid}_%");
                }
            })
            ->pluck('order_id');

        $totalUah = $allTransactions->sum(function (string $orderId): float {
            [, $days] = CheckoutService::parseOrderId($orderId);
            return $days ? (config("payments.prices.{$days}") ?? 0) / 100 : 0;
        });

        $stats = [
            'total_count' => $allTransactions->count(),
            'total_uah'   => $totalUah,
        ];

        return view('employer.billing', compact('transactions', 'stats', 'rawTransactions'));
    }
}
```

> **Примітка:** якщо в таблиці додано поле `vacancy_id` (крок 16.7 модуля 16) — замінити LIKE-пошук на простий `whereIn('vacancy_id', $vacancyIds)`. Це швидше і точніше.

---

## 📂 КРОК 18.5. Blade-шаблони

### `resources/views/employer/vacancies/extend.blade.php` — вибір тарифу

```blade
<x-employer.layout :title="'Продовжити вакансію — ' . $vacancy->title">
    <div class="max-w-3xl mx-auto px-4 py-8">

        {{-- Заголовок --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Продовжити публікацію</h1>
            <p class="mt-1 text-gray-600">
                «{{ $vacancy->title }}»
                @if($vacancy->is_active)
                    · зараз активна, завершується {{ $vacancy->expires_at->locale('uk')->isoFormat('D MMMM') }}
                @elseif($vacancy->status->value === 'expired')
                    · <span class="text-red-600 font-medium">публікація завершена</span>
                @endif
            </p>
        </div>

        {{-- Помилка від контролера --}}
        @if(session('error'))
            <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 text-red-700 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Попередження якщо скасування --}}
        @if(session('warning'))
            <div class="mb-6 rounded-lg bg-yellow-50 border border-yellow-200 p-4 text-yellow-700 text-sm">
                {{ session('warning') }}
            </div>
        @endif

        {{-- Картки тарифів --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            @foreach($plans as $plan)
                <div class="relative rounded-xl border-2 bg-white p-6
                    {{ $plan['highlight'] ? 'border-blue-500 shadow-md' : 'border-gray-200' }}">

                    @if($plan['highlight'])
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="bg-blue-500 text-white text-xs font-semibold px-3 py-1 rounded-full">
                                Найпопулярніший
                            </span>
                        </div>
                    @endif

                    <div class="text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ $plan['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-blue-600">
                            {{ number_format($plan['price_uah'], 0, '.', ' ') }} ₴
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ number_format($plan['price_uah'] / $plan['days'], 1, '.', ' ') }} ₴/день
                        </p>

                        <p class="mt-3 text-sm text-gray-600">{{ $plan['description'] }}</p>

                        <p class="mt-3 text-xs text-gray-400">
                            Активна до {{ $plan['new_expires_at']->locale('uk')->isoFormat('D MMMM YYYY') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('employer.vacancies.extend.initiate', $vacancy) }}" class="mt-6">
                        @csrf
                        <input type="hidden" name="days" value="{{ $plan['days'] }}">
                        <button type="submit"
                            class="w-full py-2.5 px-4 rounded-lg text-sm font-medium transition-colors
                                {{ $plan['highlight']
                                    ? 'bg-blue-600 hover:bg-blue-700 text-white'
                                    : 'bg-gray-100 hover:bg-gray-200 text-gray-800' }}">
                            Оплатити {{ number_format($plan['price_uah'], 0) }} ₴
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Провайдер та безпека --}}
        <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div class="text-sm text-gray-600">
                    <span class="font-medium text-gray-800">Безпечна оплата</span> через
                    @switch(config('payments.default'))
                        @case('mono')    MonoPay (Monobank Acquiring)  @break
                        @case('wayforpay') WayForPay @break
                        @case('liqpay')  LiqPay (ПриватБанк) @break
                        @case('stripe')  Stripe @break
                        @default {{ ucfirst(config('payments.default')) }}
                    @endswitch.
                    Картка не зберігається на нашому сервері.
                </div>
            </div>
        </div>

        {{-- Повернення --}}
        <div class="mt-6 text-center">
            <a href="{{ route('employer.vacancies.show', $vacancy) }}"
               class="text-sm text-gray-500 hover:text-gray-700">
                ← Повернутись до вакансії
            </a>
        </div>
    </div>
</x-employer.layout>
```

---

### `resources/views/employer/vacancies/payment-success.blade.php` — після оплати

```blade
<x-employer.layout :title="'Оплату прийнято'">
    <div class="max-w-lg mx-auto px-4 py-16 text-center">

        {{-- Іконка очікування / підтвердження --}}
        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Оплату прийнято!</h1>

        <p class="text-gray-600 mb-6">
            Ми обробляємо підтвердження від платіжного провайдера.
            Вакансія оновиться протягом хвилини — ця сторінка оновиться автоматично.
        </p>

        {{--
            Livewire-компонент перевіряє стан вакансії кожні 5 секунд.
            Після того як webhook обробив оплату і статус змінився —
            компонент показує підтвердження.
        --}}
        <livewire:employer.payment-status-poller :vacancy="$vacancy" />

        <div class="mt-8 text-sm text-gray-400">
            Якщо через 5 хвилин нічого не змінилось —
            <a href="mailto:support@myjob.ua" class="underline">напишіть нам</a>.
        </div>
    </div>
</x-employer.layout>
```

---

### `resources/views/employer/billing.blade.php` — історія платежів

```blade
<x-employer.layout :title="'Мої платежі'">
    <div class="max-w-4xl mx-auto px-4 py-8">

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Мої платежі</h1>
        <p class="text-gray-500 text-sm mb-8">Історія всіх оплат за публікації вакансій</p>

        {{-- Статистика --}}
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="rounded-lg bg-white border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Усього транзакцій</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_count'] }}</p>
            </div>
            <div class="rounded-lg bg-white border border-gray-200 p-5">
                <p class="text-sm text-gray-500">Загальна сума</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">
                    {{ number_format($stats['total_uah'], 0, '.', ' ') }} ₴
                </p>
            </div>
        </div>

        {{-- Таблиця транзакцій --}}
        @if($transactions->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="mx-auto w-12 h-12 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <p>Платежів ще немає</p>
                <p class="text-sm mt-1">Вони з'являться після першої оплати</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Дата</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Вакансія</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Тариф</th>
                            <th class="text-right px-4 py-3 font-medium text-gray-600">Сума</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Провайдер</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($transactions as $tx)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $tx->processed_at->locale('uk')->isoFormat('D MMM YYYY, HH:mm') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($tx->vacancy_id)
                                        <a href="{{ route('employer.vacancies.show', $tx->vacancy_id) }}"
                                           class="text-blue-600 hover:underline">
                                            {{ Str::limit($tx->vacancy_title, 35) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $tx->days ? $tx->days . ' днів' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">
                                    {{ $tx->amount_uah ? number_format($tx->amount_uah, 0, '.', ' ') . ' ₴' : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ match($tx->gateway) {
                                            'mono'      => 'bg-green-100 text-green-700',
                                            'wayforpay' => 'bg-blue-100 text-blue-700',
                                            'liqpay'    => 'bg-yellow-100 text-yellow-700',
                                            'stripe'    => 'bg-purple-100 text-purple-700',
                                            default     => 'bg-gray-100 text-gray-600',
                                        } }}">
                                        {{ $tx->gateway_label }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Пагінація --}}
                @if($rawTransactions->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $rawTransactions->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-employer.layout>
```

---

## 📂 КРОК 18.6. Livewire `PaymentStatusPoller`

Компонент на сторінці `payment-success` — перевіряє кожні 5 секунд чи вакансію вже продовжено.

```bash
php artisan make:livewire Employer/PaymentStatusPoller
```

`app/Livewire/Employer/PaymentStatusPoller.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Employer;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaymentStatusPoller extends Component
{
    public Vacancy $vacancy;

    /**
     * Час завантаження сторінки — щоб знати з якого моменту чекати.
     */
    public string $startedAt;

    /**
     * Скільки секунд очікувати максимум перед тим як
     * сказати «перевірте пізніше». 5 хвилин достатньо.
     */
    public int $maxWaitSeconds = 300;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy    = $vacancy;
        $this->startedAt  = now()->toIso8601String();
    }

    public function refresh(): void
    {
        $this->vacancy->refresh();
    }

    #[Computed]
    public function isConfirmed(): bool
    {
        // Вакансія вважається підтвердженою якщо expires_at оновився
        // після того як роботодавець перейшов на цю сторінку
        return $this->vacancy->status === VacancyStatus::Active
            && $this->vacancy->expires_at !== null
            && $this->vacancy->expires_at->isAfter(\Carbon\Carbon::parse($this->startedAt));
    }

    #[Computed]
    public function isTimeout(): bool
    {
        return now()->diffInSeconds(\Carbon\Carbon::parse($this->startedAt)) > $this->maxWaitSeconds;
    }

    public function render()
    {
        return view('livewire.employer.payment-status-poller');
    }
}
```

`resources/views/livewire/employer/payment-status-poller.blade.php`:

```blade
<div>
    @if($this->isConfirmed)
        {{-- Підтвердження --}}
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-center">
            <p class="font-semibold text-green-800">✅ Вакансію продовжено!</p>
            <p class="text-sm text-green-700 mt-1">
                Активна до {{ $vacancy->expires_at->locale('uk')->isoFormat('D MMMM YYYY, HH:mm') }}
            </p>
            <a href="{{ route('employer.vacancies.show', $vacancy) }}"
               class="mt-4 inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg">
                Переглянути вакансію →
            </a>
        </div>

    @elseif($this->isTimeout)
        {{-- Таймаут --}}
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-4 text-center">
            <p class="font-semibold text-yellow-800">⏳ Обробка займає більше часу</p>
            <p class="text-sm text-yellow-700 mt-1">
                Перевірте стан вакансії через кілька хвилин або зверніться до підтримки.
            </p>
            <a href="{{ route('employer.vacancies.show', $vacancy) }}"
               class="mt-4 inline-block text-sm text-yellow-700 underline">
                Перейти до вакансії
            </a>
        </div>

    @else
        {{-- Очікування --}}
        <div wire:poll.5s="refresh" class="text-center">
            <div class="inline-flex items-center gap-2 text-gray-500">
                <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-sm">Очікуємо підтвердження від провайдера...</span>
            </div>
        </div>
    @endif
</div>
```

---

## 📂 КРОК 18.7. Навігація кабінету — посилання на «Мої платежі»

У layout кабінету роботодавця `resources/views/layouts/employer.blade.php` (або component) додай:

```blade
{{-- У бічному меню або навігаційній панелі --}}
<nav>
    {{-- ... наявні пункти меню ... --}}

    <a href="{{ route('employer.billing') }}"
       class="{{ request()->routeIs('employer.billing') ? 'bg-gray-100 text-blue-600' : 'text-gray-700 hover:bg-gray-50' }}
              flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        Мої платежі
    </a>
</nav>
```

---

## 📂 КРОК 18.8. `VacancyPolicy` — авторизація

```bash
php artisan make:policy VacancyPolicy --model=Vacancy
```

`app/Policies/VacancyPolicy.php` — додай методи `extend` і `view`:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacancy;
use App\Enums\VacancyStatus;

class VacancyPolicy
{
    /**
     * Чи може роботодавець ініціювати продовження вакансії.
     */
    public function extend(User $user, Vacancy $vacancy): bool
    {
        // Тільки власник вакансії
        $isOwner = $vacancy->employer?->user_id === $user->id
            || $vacancy->user_id === $user->id; // fallback

        // Архівовані не можна продовжити
        return $isOwner && $vacancy->status !== VacancyStatus::Archived;
    }

    public function view(User $user, Vacancy $vacancy): bool
    {
        return $vacancy->employer?->user_id === $user->id
            || $vacancy->user_id === $user->id;
    }
}
```

Зареєструй у `AuthServiceProvider`:

```php
protected $policies = [
    \App\Models\Vacancy::class => \App\Policies\VacancyPolicy::class,
];
```

---

## ⚠️ Критичні нюанси

### 1. `payment-success` не гарантує що вакансію вже продовжено

Провайдер редіректить на `success_url` **одразу після оплати**, ще до відправки webhook. Webhook може прийти через 1–60 секунд. Тому `payment-success` показує «обробляємо» з `wire:poll.5s` — а не відразу «успішно». `PaymentStatusPoller` перевіряє `expires_at > startedAt` — чесна перевірка без розрахунку на статус.

### 2. `wire:poll.5s` — тільки на success-сторінці

5 секунд — агресивно, але виправдано: роботодавець чекає підтвердження і дивиться в екран. Поллінг зупиняється автоматично коли `isConfirmed` або `isTimeout` — бо компонент перестає рендерити `wire:poll`.

### 3. LIKE-пошук в `BillingController` — повільний при великій кількості вакансій

`WHERE order_id LIKE 'vac_42_%' OR order_id LIKE 'vac_57_%' OR ...` — для роботодавця з 50+ вакансіями це некомфортний запит. Вирішення:

- Якщо виконано крок 16.7 (поле `vacancy_id`) — замінити на `whereIn('vacancy_id', $vacancyIds)` з індексом.
- Або кешувати на 5 хвилин: `cache()->remember("billing.{$user->id}", 300, fn () => ...)`.

### 4. Ціни в `getPlans()` — з config, не з БД

Якщо ціни зміняться — стара сторінка вибору покаже нові ціни для старих вакансій. Це коректна поведінка: роботодавець оплачує за поточними тарифами. Але якщо потрібне grandfathering (старі клієнти платять старі ціни) — це окремий функціонал.

### 5. `VacancyPolicy::extend` vs `abort_if` в контролері

В контролері є обидва захисти: `Gate::authorize('extend', $vacancy)` (власність) і `abort_if(...archived...)`. Це правильно — Policy перевіряє власника, контролер додатково перевіряє бізнес-правило (архів). Не переноси бізнес-правила в Policy — там тільки авторизація.

### 6. Success/cancel URL формуються в `CheckoutService`

У `createVacancyExtensionCheckout()` в модулі 11A вже є:
```php
'successUrl' => route('vacancies.show', $vacancy),
'cancelUrl'  => route('vacancies.show', $vacancy),
```
Це треба змінити на:
```php
'successUrl' => route('employer.vacancies.payment.success', $vacancy),
'cancelUrl'  => route('employer.vacancies.payment.cancel', $vacancy),
```
Або передавати URL через параметри `CheckoutService::createVacancyExtensionCheckout($vacancy, $days, successUrl: ..., cancelUrl: ...)`. Уточни яким чином — залежно від архітектури CheckoutData (там поля `successUrl` і `cancelUrl` вже є).

---

## ✅ Очікуваний результат модуля

1. Маршрути `/employer/vacancies/{vacancy}/extend`, `/billing` зареєстровані.
2. `VacancyExtendController` — сторінка тарифів + ініціювання + success/cancel.
3. `BillingController` — персональна історія платежів.
4. `extend.blade.php` — 3 картки тарифів з ціна/день, рекомендований виділено.
5. `payment-success.blade.php` — `PaymentStatusPoller` з `wire:poll.5s`.
6. `billing.blade.php` — таблиця транзакцій з пагінацією і статистикою.
7. `PaymentStatusPoller` Livewire-компонент — автоматичне підтвердження або таймаут.
8. `VacancyPolicy` — методи `extend` і `view`.
9. У меню кабінету — посилання «Мої платежі».

Звіт:
```
Фінансовий UI кабінету роботодавця готовий:

  /employer/vacancies/{id}/extend      — вибір тарифу (15/30/90 днів)
  /employer/vacancies/{id}/extend POST — редірект на провайдера
  /employer/vacancies/{id}/payment/success — очікування webhook + поллінг
  /employer/vacancies/{id}/payment/cancel  — повернення з вибором тарифу
  /employer/billing                    — особиста історія платежів

PaymentStatusPoller: wire:poll.5s, підтвердження через expires_at > startedAt,
таймаут після 5 хвилин.

Чи потрібно оновити successUrl/cancelUrl у CheckoutService? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не показуй «вакансію продовжено» одразу на success-сторінці — webhook ще не прийшов.
- ❌ Не передавай `vacancy_id` у GET-параметрах URL checkout — тільки через `orderId`.
- ❌ Не дозволяй роботодавцю бачити платежі інших роботодавців — фільтр по `vacancyIds`.
- ❌ Не роби `wire:poll.1s` — достатньо 5s для очікування webhook.
- ❌ Не хардкодь ціни у Blade — тільки через `config('payments.prices')`.
- ❌ Не виконуй `migrate` без мого підтвердження.
- ❌ Не забудь оновити `successUrl`/`cancelUrl` у `CheckoutService` (нюанс #6).
