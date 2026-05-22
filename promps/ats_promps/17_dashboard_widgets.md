# МОДУЛЬ 17. Filament — Dashboard Widgets (аналітика вакансій і платежів)

## 🎯 Мета модуля
Додати на головну Filament-адмінки і на сторінку PaymentTransactions набір інформаційних віджетів:
- Статистика вакансій по статусах (картки)
- Вакансії що завершуються найближчим часом (таблиця)
- Платіжна статистика по провайдерах (картки)
- Графік платежів по днях (chart)

**Передумова:** модулі 2, 5, 16 виконано.

---

## 🔍 КРОК 17.1. Розвідка

```bash
# Які widgets вже є?
find app/Filament/Widgets -name "*.php" 2>/dev/null
ls app/Filament/Widgets/ 2>/dev/null

# Чи підключена бібліотека charts у Filament?
composer show filament/filament | grep version
# Filament v4 включає charts з коробки через @filament/charts

# Поточний dashboard
find app/Filament -name "*Dashboard*" -o -name "*dashboard*" 2>/dev/null
```

---

## 📐 Архітектура віджетів

```
Filament Dashboard (головна)
├── VacancyStatsWidget         ← 4 картки: Active / Expired / Draft / Archived
├── ExpiringSoonWidget         ← таблиця: вакансії завершуються < 72 год
└── PaymentStatsWidget         ← 3 картки: платежів сьогодні / цього місяця / за провайдерами

PaymentTransactionResource (сторінка Платежі)
└── PaymentStatsWidget         ← ті самі картки (reuse)
```

---

## 📂 КРОК 17.2. `VacancyStatsWidget` — статистика вакансій

```bash
php artisan make:filament-widget VacancyStatsWidget --stats-overview
```

`app/Filament/Widgets/VacancyStatsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VacancyStatsWidget extends BaseWidget
{
    // Оновлення кожні 60 секунд (scheduler може змінити статуси)
    protected static ?string $pollingInterval = '60s';

    // Показуємо тільки на головному дашборді
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Один запит — всі статуси одразу через groupBy
        $counts = Vacancy::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $active   = $counts[VacancyStatus::Active->value]   ?? 0;
        $expired  = $counts[VacancyStatus::Expired->value]  ?? 0;
        $draft    = $counts[VacancyStatus::Draft->value]    ?? 0;
        $archived = $counts[VacancyStatus::Archived->value] ?? 0;

        // Скільки активних завершується < 24 год (критичних)
        $criticalCount = Vacancy::expiringSoon(24)->count();

        return [
            Stat::make('Активні вакансії', $active)
                ->description($criticalCount > 0
                    ? "{$criticalCount} завершуються < 24 год ⚠"
                    : 'Публікуються зараз'
                )
                ->descriptionIcon($criticalCount > 0
                    ? 'heroicon-m-exclamation-triangle'
                    : 'heroicon-m-check-circle'
                )
                ->color($criticalCount > 0 ? 'warning' : 'success')
                ->chart($this->getActiveVacanciesChart()),

            Stat::make('Завершені', $expired)
                ->description('Доступні за URL (noindex)')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Чернетки', $draft)
                ->description('Не опубліковані')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('gray'),

            Stat::make('В архіві', $archived)
                ->description('404 за прямим URL')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('danger'),
        ];
    }

    /**
     * Мінічарт: кількість вакансій опублікованих по днях за останні 7 днів.
     * Показується як спарклайн під карткою «Активні».
     */
    private function getActiveVacanciesChart(): array
    {
        return Vacancy::query()
            ->where('status', VacancyStatus::Active)
            ->where('published_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(published_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->values()
            ->toArray();
    }
}
```

---

## 📂 КРОК 17.3. `ExpiringSoonWidget` — вакансії що завершуються

```bash
php artisan make:filament-widget ExpiringSoonWidget --table
```

`app/Filament/Widgets/ExpiringSoonWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringSoonWidget extends BaseWidget
{
    protected static ?string $heading = 'Вакансії що завершуються (< 72 год)';

    protected static ?int $sort = 2;

    // Показувати тільки якщо є такі вакансії
    public static function canView(): bool
    {
        return Vacancy::expiringSoon(72)->exists();
    }

    protected static ?string $pollingInterval = '300s'; // оновлення кожні 5 хвилин

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vacancy::expiringSoon(72)
                    ->with('employer') // підлаштуй під свою структуру
                    ->orderBy('expires_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Вакансія')
                    ->limit(35)
                    ->url(fn (Vacancy $record): string =>
                        route('filament.admin.resources.vacancies.edit', $record)
                    ),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Завершується')
                    ->dateTime('d.m.Y H:i')
                    ->color(fn (Vacancy $record): string =>
                        $record->hours_left !== null && $record->hours_left < 24
                            ? 'danger'
                            : 'warning'
                    ),

                Tables\Columns\TextColumn::make('countdown_label')
                    ->label('Залишок')
                    ->badge()
                    ->color(fn (Vacancy $record): string =>
                        $record->hours_left !== null && $record->hours_left < 24
                            ? 'danger'
                            : 'warning'
                    ),

                Tables\Columns\TextColumn::make('expiry_notification_sent_at')
                    ->label('Нотифіковано')
                    ->state(fn (Vacancy $record): string =>
                        $record->expiry_notification_sent_at
                            ? '✅ ' . $record->expiry_notification_sent_at->format('H:i')
                            : '⏳ Ні'
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('extend_30')
                    ->label('+30 днів')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(fn (Vacancy $record) => $record->extend(30))
                    ->successNotificationTitle('Вакансію продовжено'),

                Tables\Actions\Action::make('archive')
                    ->label('Архів')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Vacancy $record) => $record->archive()),
            ])
            ->emptyStateHeading('Немає вакансій що завершуються')
            ->paginated(false); // показуємо всі без пагінації (їх не може бути 1000)
    }
}
```

---

## 📂 КРОК 17.4. `PaymentStatsWidget` — статистика платежів

```bash
php artisan make:filament-widget PaymentStatsWidget --stats-overview
```

`app/Filament/Widgets/PaymentStatsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PaymentTransaction;
use App\Payments\CheckoutService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PaymentStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';
    protected static ?int    $sort = 3;

    protected function getStats(): array
    {
        $today     = $this->getRevenueForPeriod('today');
        $thisMonth = $this->getRevenueForPeriod('month');
        $byGateway = $this->getCountByGateway();

        // Тренд: цей місяць vs минулий
        $lastMonth    = $this->getRevenueForPeriod('last_month');
        $trend        = $lastMonth > 0
            ? round(($thisMonth['total'] - $lastMonth['total']) / $lastMonth['total'] * 100)
            : null;
        $trendLabel   = $trend !== null
            ? ($trend >= 0 ? "+{$trend}% vs минулий місяць" : "{$trend}% vs минулий місяць")
            : 'Перший місяць';
        $trendColor   = ($trend ?? 0) >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Платежів сьогодні', $today['count'])
                ->description(number_format($today['total'], 0, '.', ' ') . ' ₴')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Дохід цього місяця', number_format($thisMonth['total'], 0, '.', ' ') . ' ₴')
                ->description("{$thisMonth['count']} транзакцій · {$trendLabel}")
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trendColor)
                ->chart($this->getDailyRevenueChart()),

            Stat::make('По провайдерах', $this->formatGatewayBreakdown($byGateway))
                ->description('За поточний місяць')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('info'),
        ];
    }

    // =========================================================================
    // Допоміжні методи
    // =========================================================================

    /**
     * Підраховуємо кількість і суму транзакцій за період.
     * Сума береться з config по тарифу (15/30/90 днів).
     * Якщо обрано крок 16.7 (amount_kopecks у таблиці) — замінити на SUM(amount_kopecks)/100.
     */
    private function getRevenueForPeriod(string $period): array
    {
        $query = PaymentTransaction::query();

        $query = match ($period) {
            'today'      => $query->whereDate('processed_at', today()),
            'month'      => $query->whereMonth('processed_at', now()->month)
                                  ->whereYear('processed_at', now()->year),
            'last_month' => $query->whereMonth('processed_at', now()->subMonth()->month)
                                  ->whereYear('processed_at', now()->subMonth()->year),
            default      => $query,
        };

        $transactions = $query->pluck('order_id');
        $count = $transactions->count();

        // Підраховуємо суму через декодування order_id
        $total = $transactions->sum(function (string $orderId): float {
            [, $days] = CheckoutService::parseOrderId($orderId);
            if (! $days) return 0;
            return (config("payments.prices.{$days}") ?? 0) / 100;
        });

        return compact('count', 'total');
    }

    /**
     * Кількість транзакцій по провайдерах за поточний місяць.
     */
    private function getCountByGateway(): array
    {
        return PaymentTransaction::query()
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->select('gateway', DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->pluck('count', 'gateway')
            ->toArray();
    }

    /**
     * Компактний рядок: "MonoPay: 12, LiqPay: 5"
     */
    private function formatGatewayBreakdown(array $byGateway): string
    {
        if (empty($byGateway)) {
            return 'Немає даних';
        }

        $labels = [
            'mono'      => 'MonoPay',
            'wayforpay' => 'WayForPay',
            'liqpay'    => 'LiqPay',
            'stripe'    => 'Stripe',
        ];

        return collect($byGateway)
            ->map(fn ($count, $gw) => ($labels[$gw] ?? $gw) . ': ' . $count)
            ->implode(', ');
    }

    /**
     * Спарклайн: дохід по днях за останні 14 днів.
     */
    private function getDailyRevenueChart(): array
    {
        $rows = PaymentTransaction::query()
            ->where('processed_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(processed_at) as date, GROUP_CONCAT(order_id) as order_ids')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(function ($row): float {
            $total = 0;
            foreach (explode(',', $row->order_ids) as $orderId) {
                [, $days] = CheckoutService::parseOrderId($orderId);
                if ($days) {
                    $total += (config("payments.prices.{$days}") ?? 0) / 100;
                }
            }
            return $total;
        })->values()->toArray();
    }
}
```

---

## 📂 КРОК 17.5. `PaymentGatewayChartWidget` — графік по провайдерах

```bash
php artisan make:filament-widget PaymentGatewayChartWidget --chart
```

`app/Filament/Widgets/PaymentGatewayChartWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentGatewayChartWidget extends ChartWidget
{
    protected static ?string $heading      = 'Платежі по днях';
    protected static ?int    $sort         = 4;
    protected static ?string $maxHeight    = '200px';
    protected static ?string $pollingInterval = '600s'; // 10 хв

    public string $filter = '30'; // за замовчуванням — 30 днів

    protected function getFilters(): ?array
    {
        return [
            '7'  => 'Останні 7 днів',
            '30' => 'Останні 30 днів',
            '90' => 'Останні 90 днів',
        ];
    }

    protected function getData(): array
    {
        $days   = (int) $this->filter;
        $labels = [];
        $datasets = [];

        $gateways = ['mono' => 'MonoPay', 'wayforpay' => 'WayForPay', 'liqpay' => 'LiqPay', 'stripe' => 'Stripe'];
        $colors   = [
            'mono'      => 'rgb(34, 197, 94)',
            'wayforpay' => 'rgb(59, 130, 246)',
            'liqpay'    => 'rgb(234, 179, 8)',
            'stripe'    => 'rgb(139, 92, 246)',
        ];

        // Генеруємо мітки дат
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('d.m');
        }

        // Запит: кількість транзакцій по кожному провайдеру по кожному дню
        $data = PaymentTransaction::query()
            ->where('processed_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(processed_at) as date, gateway, COUNT(*) as count')
            ->groupBy('date', 'gateway')
            ->orderBy('date')
            ->get()
            ->groupBy('gateway');

        foreach ($gateways as $gwKey => $gwLabel) {
            $gwData = $data->get($gwKey, collect());

            // Індексуємо по даті для швидкого пошуку
            $indexed = $gwData->pluck('count', 'date')->toArray();

            $values = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date     = now()->subDays($i)->format('Y-m-d');
                $values[] = $indexed[$date] ?? 0;
            }

            // Показуємо провайдер тільки якщо він взагалі є в даних
            if (array_sum($values) > 0) {
                $datasets[] = [
                    'label'           => $gwLabel,
                    'data'            => $values,
                    'borderColor'     => $colors[$gwKey],
                    'backgroundColor' => str_replace('rgb', 'rgba', $colors[$gwKey]) . str_replace(')', ', 0.1)', ''),
                    'fill'            => true,
                    'tension'         => 0.3,
                ];
            }
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## 📂 КРОК 17.6. Реєстрація на головній панелі

Відкрий провайдер панелі `app/Providers/Filament/AdminPanelProvider.php` і додай:

```php
->widgets([
    \Filament\Widgets\AccountWidget::class,
    \App\Filament\Widgets\VacancyStatsWidget::class,
    \App\Filament\Widgets\ExpiringSoonWidget::class,
    \App\Filament\Widgets\PaymentStatsWidget::class,
    \App\Filament\Widgets\PaymentGatewayChartWidget::class,
])
```

Або якщо використовуєш auto-discovery — нічого не треба (Filament підхопить автоматично).

---

## 📂 КРОК 17.7. `VacancyResource` — HeaderWidgets на сторінці списку

У `ListVacancies` додай:

```php
protected function getHeaderWidgets(): array
{
    return [
        \App\Filament\Widgets\VacancyStatsWidget::class,
        \App\Filament\Widgets\ExpiringSoonWidget::class,
    ];
}
```

---

## ⚠️ Критичні нюанси

### 1. `getStats()` — один запит, не чотири

У `VacancyStatsWidget` я роблю один `SELECT status, COUNT(*) GROUP BY status` і розбираю результат у PHP. Це O(1) запит. Якщо зробити `Vacancy::active()->count()`, `Vacancy::expired()->count()` і т.д. — чотири окремих запити. На великій базі різниця суттєва.

### 2. `canView()` в `ExpiringSoonWidget`

Якщо немає вакансій < 72 год — віджет просто не рендериться. Це чистіше ніж показувати порожній блок з написом «тут нічого немає».

### 3. Сума в `PaymentStatsWidget` — теж з config

Та сама проблема що і в модулі 16: якщо ціни змінились — статистика покаже неправильну суму за старі транзакції. Якщо виконано крок 16.7 (додано `amount_kopecks` в таблицю) — замінити `getRevenueForPeriod()` на простий `SUM(amount_kopecks)/100`.

**З `amount_kopecks` в таблиці:**
```php
private function getRevenueForPeriod(string $period): array
{
    $query = PaymentTransaction::query();
    $query = match ($period) {
        'today'      => $query->whereDate('processed_at', today()),
        'month'      => $query->whereMonth('processed_at', now()->month)->whereYear('processed_at', now()->year),
        'last_month' => $query->whereMonth('processed_at', now()->subMonth()->month)->whereYear('processed_at', now()->subMonth()->year),
    };
    return [
        'count' => $query->count(),
        'total' => $query->sum('amount_kopecks') / 100,
    ];
}
```

### 4. `GROUP_CONCAT` у `getDailyRevenueChart`

`GROUP_CONCAT(order_id)` — MySQL-специфічна функція. Якщо проєкт на PostgreSQL — замінити на `STRING_AGG(order_id, ',')`. Або перенести агрегацію в PHP через окремий запит.

### 5. `pollingInterval` — не занадто часто

- `VacancyStatsWidget`: 60s — після кожного запуску scheduler можуть змінитися статуси.
- `ExpiringSoonWidget`: 300s — рідше, бо терміни не міняються за хвилини.
- `PaymentGatewayChartWidget`: 600s — графік за місяць не потребує частого оновлення.

### 6. Чистота чарту — показуємо тільки активні провайдери

У `PaymentGatewayChartWidget` провайдер з `array_sum($values) === 0` не потрапляє в датасет. Якщо використовується тільки MonoPay — на графіку лише одна лінія.

### 7. `$sort` — порядок виводу на Dashboard

```
1 → VacancyStatsWidget     (найважливіше)
2 → ExpiringSoonWidget      (термінове)
3 → PaymentStatsWidget      (гроші)
4 → PaymentGatewayChartWidget (аналітика)
```

Змінюй `$sort` якщо треба інший порядок.

---

## 🧪 КРОК 17.8. Перевірка

```bash
# 1. Створити тестові дані для перевірки
php artisan tinker
```
```php
// Вакансії у різних статусах
\App\Models\Vacancy::factory()->active(daysLeft: 30)->count(5)->create();
\App\Models\Vacancy::factory()->active(daysLeft: 1)->count(2)->create();  // критичні
\App\Models\Vacancy::factory()->expired()->count(3)->create();
\App\Models\Vacancy::factory()->draft()->count(4)->create();

// Тестові платежі (вручну, бо webhook-тести вже є в модулі 15)
$vacancy = \App\Models\Vacancy::first();
\Illuminate\Support\Facades\DB::table('payment_processed_events')->insert([
    ['event_id' => 'mono_test_1', 'gateway' => 'mono', 'order_id' => \App\Payments\CheckoutService::buildOrderId($vacancy->id, 30), 'processed_at' => now()],
    ['event_id' => 'liqpay_test_1', 'gateway' => 'liqpay', 'order_id' => \App\Payments\CheckoutService::buildOrderId($vacancy->id, 15), 'processed_at' => now()->subDays(2)],
    ['event_id' => 'mono_test_2', 'gateway' => 'mono', 'order_id' => \App\Payments\CheckoutService::buildOrderId($vacancy->id, 90), 'processed_at' => now()->subDays(5)],
]);
```

```bash
# 2. Відкрий адмінку
# http://localhost:8000/admin

# Перевір:
# ✅ VacancyStatsWidget: Active=7, Expired=3, Draft=4, "2 завершуються < 24 год ⚠"
# ✅ ExpiringSoonWidget: 2 вакансії у таблиці (з кнопками +30 і Архів)
# ✅ PaymentStatsWidget: 1 транзакція сьогодні, 3 загалом, "MonoPay: 2, LiqPay: 1"
# ✅ PaymentGatewayChartWidget: дві лінії (MonoPay зелена, LiqPay жовта)

# 3. Перевір що ExpiringSoonWidget зникає після expire всіх < 72 год вакансій
php artisan vacancies:expire
# Після цього ExpiringSoonWidget не показується (canView() = false)
```

---

## ✅ Очікуваний результат модуля

1. `VacancyStatsWidget` — 4 картки зі спарклайном публікацій за 7 днів.
2. `ExpiringSoonWidget` — таблиця вакансій < 72 год з діями extend/archive.
3. `PaymentStatsWidget` — 3 картки: сьогодні / місяць / по провайдерах.
4. `PaymentGatewayChartWidget` — лінійний графік по провайдерах за 7/30/90 днів.
5. Всі 4 віджети підключені на головному Dashboard.
6. `VacancyStatsWidget` і `ExpiringSoonWidget` також на сторінці ListVacancies.
7. `PaymentStatsWidget` також на сторінці ListPaymentTransactions (з модуля 16).

Звіт:
```
4 Filament-віджети готові:
  VacancyStatsWidget     — статус вакансій, спарклайн, 60s poll
  ExpiringSoonWidget     — термінові вакансії, дії extend/archive, canView()
  PaymentStatsWidget     — дохід сьогодні/місяць/провайдери, 300s poll
  PaymentGatewayChartWidget — лінійний чарт, фільтр 7/30/90 днів, 600s poll

Фінальна структура Filament-адмінки:
  Dashboard:
    ├── VacancyStatsWidget
    ├── ExpiringSoonWidget (умовно)
    ├── PaymentStatsWidget
    └── PaymentGatewayChartWidget
  Ресурси:
    ├── Вакансії (VacancyResource) → HeaderWidgets: Stats + ExpiringSoon
    └── Фінанси → Платежі (PaymentTransactionResource) → HeaderWidgets: PaymentStats
```

---

## 🚨 Чого НЕ робити

- ❌ Не роби окремий `COUNT(*)` для кожного статусу — один `GROUP BY status` запит.
- ❌ Не встановлюй `pollingInterval = '1s'` — це DDoS на власний сервер.
- ❌ Не використовуй `Vacancy::all()` у `getStats()` — тільки агрегатні запити.
- ❌ Не показуй `ExpiringSoonWidget` якщо таких вакансій немає — `canView()` вирішує.
- ❌ Не хардкодь кольори провайдерів у CSS — тільки через масив у `getData()`.
- ❌ Не рахуй суму доходу без урахування того що ціни змінились (крок 16.7).
