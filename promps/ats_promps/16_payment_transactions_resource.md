# МОДУЛЬ 16. Filament — `PaymentTransactionsResource` (перегляд платежів в адмінці)

## 🎯 Мета модуля
Додати в Filament-адмінку read-only таблицю всіх оброблених платежів з `payment_processed_events`. Адміністратор бачить: провайдера, суму (декодовану з `order_id`), вакансію, дату, може шукати по `order_id` і фільтрувати по провайдеру та дату.

**Передумова:** модулі 5 і 11A виконано. Таблиця `payment_processed_events` існує і має записи.

---

## 🔍 КРОК 16.1. Розвідка

```bash
# 1. Перевір, чи є записи в таблиці
php artisan tinker --execute="dump(DB::table('payment_processed_events')->count());"

# 2. Переглянь кілька записів щоб зрозуміти реальний формат order_id
php artisan tinker --execute="dump(DB::table('payment_processed_events')->latest('processed_at')->limit(3)->get());"

# 3. Яка панель Filament використовується (AdminPanel, EmployerPanel?)
find app/Providers/Filament -name "*.php" | head -5
ls app/Filament/

# 4. У якій папці VacancyResource (з модуля 5)?
find app/Filament -name "VacancyResource.php"
```

Відзвітуй — я підлаштую namespace.

---

## 📐 КРОК 16.2. Модель `PaymentTransaction`

Таблиця `payment_processed_events` не має Eloquent-моделі. Створимо — це спрощує Filament-ресурс і дозволяє додати обчислювані атрибути.

`app/Models/PaymentTransaction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Payments\CheckoutService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Readonly-модель для таблиці payment_processed_events.
 * Тільки читання — не пишемо через модель, тільки через DB::table() у WebhookController.
 *
 * @property string      $event_id
 * @property string      $gateway
 * @property string      $order_id
 * @property \Carbon\Carbon $processed_at
 * @property-read int|null   $vacancy_id
 * @property-read int|null   $days
 * @property-read float|null $amount_uah
 * @property-read string     $gateway_label
 */
class PaymentTransaction extends Model
{
    protected $table      = 'payment_processed_events';
    protected $primaryKey = 'event_id';     // string PK
    public $incrementing  = false;
    public $keyType       = 'string';
    public $timestamps    = false;          // немає created_at/updated_at

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    // Модель тільки для читання
    protected static function booted(): void
    {
        static::creating(fn () => false);
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    // =========================================================================
    // Computed accessors
    // =========================================================================

    /**
     * Декодуємо vacancy_id з order_id.
     * Формат: vac_{vacancyId}_{days}_{suffix}
     */
    protected function vacancyId(): Attribute
    {
        return Attribute::get(function (): ?int {
            [$vacancyId] = CheckoutService::parseOrderId($this->order_id);
            return $vacancyId;
        });
    }

    /**
     * Кількість днів продовження з order_id.
     */
    protected function days(): Attribute
    {
        return Attribute::get(function (): ?int {
            [, $days] = CheckoutService::parseOrderId($this->order_id);
            return $days;
        });
    }

    /**
     * Сума в гривнях — береться з config за кількістю днів.
     * Якщо ціну змінили після транзакції — покаже поточну, а не фактичну.
     * Для фактичної суми потрібно розширити таблицю (дивись КРОК 16.7).
     */
    protected function amountUah(): Attribute
    {
        return Attribute::get(function (): ?float {
            if (! $this->days) {
                return null;
            }
            $kopecks = config("payments.prices.{$this->days}");
            return $kopecks ? $kopecks / 100 : null;
        });
    }

    /**
     * Людський лейбл провайдера.
     */
    protected function gatewayLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->gateway) {
            'mono'       => 'MonoPay',
            'wayforpay'  => 'WayForPay',
            'liqpay'     => 'LiqPay',
            'stripe'     => 'Stripe',
            default      => ucfirst($this->gateway),
        });
    }

    // =========================================================================
    // Relations
    // =========================================================================

    public function vacancy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vacancy::class, 'vacancy_id_virtual')
            ->withDefault(); // якщо vacancy видалено — не кидає помилку
    }

    /**
     * Завантажує пов'язану вакансію через vacancy_id з order_id.
     * Стандартний belongsTo() не підходить — FK не в таблиці.
     */
    public function getVacancyAttribute(): ?Vacancy
    {
        if (! $this->vacancy_id) {
            return null;
        }
        return Vacancy::find($this->vacancy_id);
    }
}
```

> **Важливо:** `getVacancyAttribute` — це N+1. У Filament-таблиці компенсуємо через `->state(fn ($record) => ...)` без lazy-loading, або через окрему eager-load стратегію в `getEloquentQuery()`.

---

## 📂 КРОК 16.3. Filament Resource

```bash
php artisan make:filament-resource PaymentTransaction --view-only
```

`app/Filament/Resources/PaymentTransactionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTransactionResource\Pages;
use App\Models\PaymentTransaction;
use App\Models\Vacancy;
use App\Payments\CheckoutService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Платежі';
    protected static ?string $navigationGroup = 'Фінанси';
    protected static ?int    $navigationSort  = 10;

    /**
     * Цей ресурс — тільки для читання.
     * Форми редагування немає — Filament показує тільки таблицю + view.
     */
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        // View-only деталі транзакції
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('Провайдер')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mono'      => 'success',
                        'wayforpay' => 'info',
                        'liqpay'    => 'warning',
                        'stripe'    => 'primary',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'mono'      => 'MonoPay',
                        'wayforpay' => 'WayForPay',
                        'liqpay'    => 'LiqPay',
                        'stripe'    => 'Stripe',
                        default     => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('amount_uah')
                    ->label('Сума')
                    ->state(function (PaymentTransaction $record): string {
                        $amount = $record->amount_uah;
                        return $amount !== null ? number_format($amount, 0, '.', ' ') . ' ₴' : '—';
                    })
                    ->sortable(false), // обчислюється, не в БД

                Tables\Columns\TextColumn::make('days')
                    ->label('Днів')
                    ->state(fn (PaymentTransaction $record): string =>
                        $record->days ? "{$record->days} д." : '—'
                    ),

                Tables\Columns\TextColumn::make('vacancy_title')
                    ->label('Вакансія')
                    ->state(function (PaymentTransaction $record): string {
                        if (! $record->vacancy_id) {
                            return "#{$record->order_id}";
                        }
                        // Кешуємо в рамках запиту
                        static $vacancyCache = [];
                        if (! isset($vacancyCache[$record->vacancy_id])) {
                            $vacancyCache[$record->vacancy_id] = Vacancy::find($record->vacancy_id);
                        }
                        $vacancy = $vacancyCache[$record->vacancy_id];
                        return $vacancy
                            ? "#{$vacancy->id} {$vacancy->title}"
                            : "#{$record->vacancy_id} (видалено)";
                    })
                    ->searchable(false)
                    ->limit(40),

                Tables\Columns\TextColumn::make('event_id')
                    ->label('ID події')
                    ->limit(24)
                    ->tooltip(fn (PaymentTransaction $record): string => $record->event_id)
                    ->copyable()
                    ->copyMessage('ID скопійовано')
                    ->searchable(),

                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order ID')
                    ->limit(20)
                    ->tooltip(fn (PaymentTransaction $record): string => $record->order_id)
                    ->copyable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->label('Провайдер')
                    ->options([
                        'mono'      => 'MonoPay',
                        'wayforpay' => 'WayForPay',
                        'liqpay'    => 'LiqPay',
                        'stripe'    => 'Stripe',
                    ]),

                SelectFilter::make('days')
                    ->label('Тариф')
                    ->options([
                        '15' => '15 днів',
                        '30' => '30 днів',
                        '90' => '90 днів',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }
                        // Фільтруємо по суфіксу orderId: vac_*_{days}_*
                        return $query->where('order_id', 'LIKE', "vac\_%\_{$data['value']}\_%");
                    }),

                Filter::make('processed_at')
                    ->label('Період')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('З')
                            ->displayFormat('d.m.Y'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('По')
                            ->displayFormat('d.m.Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($q) => $q->whereDate('processed_at', '>=', $data['from'])
                            )
                            ->when(
                                $data['until'],
                                fn ($q) => $q->whereDate('processed_at', '<=', $data['until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'З: ' . Carbon::parse($data['from'])->format('d.m.Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'По: ' . Carbon::parse($data['until'])->format('d.m.Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Деталі'),
            ])
            ->defaultSort('processed_at', 'desc')
            ->poll('60s')               // автооновлення — нові платежі з'являються без F5
            ->striped()
            ->emptyStateHeading('Платежів ще немає')
            ->emptyStateDescription('Тут з\'являться транзакції після першої оплати.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTransactions::route('/'),
            'view'  => Pages\ViewPaymentTransaction::route('/{record}'),
        ];
    }

    /**
     * Кастомний запит — для сортування по processed_at за замовчуванням
     * і можливого майбутнього scope (наприклад, тільки поточний місяць).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->latest('processed_at');
    }
}
```

---

## 📂 КРОК 16.4. Сторінки ресурсу

### `ListPaymentTransactions`

```bash
php artisan make:filament-page ListPaymentTransactions --resource=PaymentTransactionResource --type=ListRecords
```

`app/Filament/Resources/PaymentTransactionResource/Pages/ListPaymentTransactions.php`:

```php
<?php

namespace App\Filament\Resources\PaymentTransactionResource\Pages;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPaymentTransactions extends ListRecords
{
    protected static string $resource = PaymentTransactionResource::class;

    /**
     * Підзаголовок зі статистикою поточного місяця.
     */
    public function getSubheading(): ?string
    {
        $thisMonth = PaymentTransaction::whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->count();

        return "Цього місяця: {$thisMonth} транзакцій";
    }

    protected function getHeaderActions(): array
    {
        return []; // readonly — немає кнопки Create
    }

    /**
     * Header widgets — підсумки над таблицею (з модуля 17).
     */
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\PaymentStatsWidget::class,
        ];
    }
}
```

### `ViewPaymentTransaction`

```bash
php artisan make:filament-page ViewPaymentTransaction --resource=PaymentTransactionResource --type=ViewRecord
```

`app/Filament/Resources/PaymentTransactionResource/Pages/ViewPaymentTransaction.php`:

```php
<?php

namespace App\Filament\Resources\PaymentTransactionResource\Pages;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\Vacancy;
use App\Payments\CheckoutService;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentTransaction extends ViewRecord
{
    protected static string $resource = PaymentTransactionResource::class;

    /**
     * Infolist — детальна картка транзакції (тільки читання).
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Транзакція')
                ->columns(2)
                ->schema([
                    TextEntry::make('event_id')
                        ->label('ID події')
                        ->copyable(),

                    TextEntry::make('gateway')
                        ->label('Провайдер')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'mono'      => 'success',
                            'wayforpay' => 'info',
                            'liqpay'    => 'warning',
                            'stripe'    => 'primary',
                            default     => 'gray',
                        })
                        ->formatStateUsing(fn (string $state) => match ($state) {
                            'mono'      => 'MonoPay',
                            'wayforpay' => 'WayForPay',
                            'liqpay'    => 'LiqPay',
                            'stripe'    => 'Stripe',
                            default     => ucfirst($state),
                        }),

                    TextEntry::make('order_id')
                        ->label('Order ID')
                        ->copyable(),

                    TextEntry::make('processed_at')
                        ->label('Оброблено')
                        ->dateTime('d.m.Y H:i:s'),
                ]),

            Section::make('Деталі оплати')
                ->columns(2)
                ->schema([
                    TextEntry::make('days_label')
                        ->label('Тариф')
                        ->state(function ($record): string {
                            [, $days] = CheckoutService::parseOrderId($record->order_id);
                            return $days ? "{$days} днів" : '—';
                        }),

                    TextEntry::make('amount_label')
                        ->label('Сума')
                        ->state(function ($record): string {
                            [, $days] = CheckoutService::parseOrderId($record->order_id);
                            if (! $days) return '—';
                            $kopecks = config("payments.prices.{$days}");
                            return $kopecks
                                ? number_format($kopecks / 100, 0, '.', ' ') . ' ₴'
                                : '—';
                        }),
                ]),

            Section::make('Вакансія')
                ->schema([
                    TextEntry::make('vacancy_info')
                        ->label('Вакансія')
                        ->state(function ($record): string {
                            [$vacancyId] = CheckoutService::parseOrderId($record->order_id);
                            if (! $vacancyId) return 'Невідомо';

                            $vacancy = Vacancy::find($vacancyId);
                            return $vacancy
                                ? "#{$vacancy->id} — {$vacancy->title} ({$vacancy->status->label()})"
                                : "#{$vacancyId} (вакансію видалено)";
                        })
                        ->url(function ($record): ?string {
                            [$vacancyId] = CheckoutService::parseOrderId($record->order_id);
                            if (! $vacancyId) return null;
                            return route('filament.admin.resources.vacancies.edit', $vacancyId);
                        }),
                ]),
        ]);
    }
}
```

---

## 📂 КРОК 16.5. Navigation Badge — лічильник нових платежів

У `PaymentTransactionResource` додай:

```php
public static function getNavigationBadge(): ?string
{
    // Кількість транзакцій за останні 24 год — як індикатор активності
    $count = static::getModel()::where('processed_at', '>=', now()->subDay())->count();
    return $count > 0 ? (string) $count : null;
}

public static function getNavigationBadgeColor(): string|array|null
{
    return 'success';
}
```

---

## ⚠️ КРОК 16.6. Критичні нюанси

### 1. Сума береться з поточного `config`, не з транзакції

`payment_processed_events` не зберігає фактичну суму — тільки `order_id`. Сума відтворюється через `config('payments.prices.{days}')`. Це означає: якщо ціни зміняться — у старих транзакціях покажеться нова ціна.

**Якщо це неприйнятно** — розширити таблицю (дивись крок 16.7).

### 2. N+1 для vacancy_title

У колонці `vacancy_title` є закешований static масив усередині `state()`. Це дешевий патч. Правильне рішення — `getEloquentQuery()` з `addSelect` через subquery. Але оскільки vacancy_id не в таблиці (він у order_id) — subquery складний. Для 100–500 рядків таблиці static cache достатній.

### 3. `->poll('60s')` — автооновлення

Таблиця оновлюється щохвилини. Адмін побачить нові платежі без F5. Прибери якщо навантаження неприйнятне.

### 4. `canCreate/Edit/Delete` — readonly

Транзакції не можна редагувати або видаляти через UI. Це аудитна таблиця. Якщо потрібне видалення старих записів — тільки через artisan-команду або scheduled cleanup.

### 5. LIKE-фільтр по `days` з orderId

Фільтр «Тариф» робить `LIKE 'vac_\_%\_30\_%'`. Backslash для escape в MySQL. При великій кількості записів (10 000+) — додай індекс на `order_id` (вже є з модуля 11A).

---

## 📂 КРОК 16.7. Опційне розширення таблиці (якщо потрібна фактична сума)

Якщо хочеш зберігати реальну суму і vacancy_id в таблиці — додай міграцію:

```bash
php artisan make:migration add_amount_to_payment_processed_events_table
```

```php
Schema::table('payment_processed_events', function (Blueprint $table) {
    $table->unsignedInteger('amount_kopecks')->nullable()->after('order_id');
    $table->unsignedBigInteger('vacancy_id')->nullable()->after('amount_kopecks');
    $table->index('vacancy_id');
});
```

І у `WebhookController::markProcessed()` додай:

```php
private function markProcessed(string $eventId, string $gateway, string $orderId, PaymentResult $result): void
{
    [$vacancyId] = CheckoutService::parseOrderId($orderId);

    DB::table('payment_processed_events')->insert([
        'event_id'      => $eventId,
        'gateway'       => $gateway,
        'order_id'      => $orderId,
        'amount_kopecks' => $result->amountKopecks,
        'vacancy_id'    => $vacancyId,
        'processed_at'  => now(),
    ]);
}
```

Тоді в моделі `PaymentTransaction` замінюй computed accessors на прямі casts.

**Це рекомендовано, якщо:**
- Ціни міняються і потрібна точна сума з кожної транзакції
- Потрібна аналітика «скільки платежів по якій вакансії» (vacancy_id як FK)
- Таблиця буде рости > 10 000 рядків

---

## ✅ Очікуваний результат модуля

1. `app/Models/PaymentTransaction.php` — Eloquent-модель на `payment_processed_events`.
2. `app/Filament/Resources/PaymentTransactionResource.php` — ресурс з таблицею.
3. `app/Filament/Resources/PaymentTransactionResource/Pages/ListPaymentTransactions.php`.
4. `app/Filament/Resources/PaymentTransactionResource/Pages/ViewPaymentTransaction.php`.
5. У навігації Filament з'явився розділ **Фінанси → Платежі** з лічильником за 24 год.
6. Таблиця: провайдер (бейдж), сума, днів, вакансія, event_id, order_id.
7. Фільтри: провайдер, тариф (15/30/90 днів), діапазон дат.
8. Детальна картка транзакції через View-сторінку.

Звіт:
```
PaymentTransactionResource готовий.
Таблиця: 7 колонок, 3 фільтри, автооновлення 60s.
View-сторінка: інформація про транзакцію + посилання на вакансію.
Navigation badge: кількість платежів за 24 год.

Перейти до модуля 17 (Dashboard widgets)? (так/ні)
```

---

## 🚨 Чого НЕ робити

- ❌ Не додавай `canCreate/Edit` — це аудитна таблиця, тільки читання.
- ❌ Не сортуй таблицю по `amount_uah` — це computed, не в БД, ORDER BY не спрацює.
- ❌ Не використовуй `Vacancy::all()` у `state()` — тільки `Vacancy::find($id)` з кешем.
- ❌ Не виконуй `migrate` без мого підтвердження (якщо обираєш крок 16.7).
- ❌ Не додавай Policy з обмеженням по роботодавцю — це таблиця для адмінів, не для роботодавців.
