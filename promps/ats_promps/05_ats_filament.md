# МОДУЛЬ 5. Filament v4 — VacancyResource

## 🎯 Мета
Адмінка для роботодавця з повним керуванням життєвим циклом вакансії: форма редагування дат, таблиця з фільтрами/бейджами, дії extend/archive, групові дії.

**Передумова:** модулі 1–4 виконано.

---

## 🔍 Розвідка

```bash
# Версія Filament
composer show filament/filament | grep versions

# Чи є вже ресурс?
ls app/Filament/Resources/ | grep -i vacanc
ls app/Filament/Employer/Resources/ 2>/dev/null  # якщо панель роботодавця окрема
```

**Запитай мене:** в яку панель додавати — головну `app/Filament/Resources/` чи окрему `app/Filament/Employer/Resources/` для роботодавців?

---

## 📂 Команда генерації

```bash
php artisan make:filament-resource Vacancy --generate
```

Або, якщо існує — оновлюй точково.

---

## 🧩 Form (форма редагування)

`VacancyResource::form()`:

```php
use Filament\Forms;
use Filament\Forms\Form;
use App\Enums\VacancyStatus;

public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Section::make('Основна інформація')
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Назва вакансії')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('description')
                    ->label('Опис')
                    ->required()
                    ->columnSpanFull(),
                // ... інші наявні поля
            ]),

        Forms\Components\Section::make('Життєвий цикл')
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options(VacancyStatus::options())
                    ->default(VacancyStatus::Draft->value)
                    ->required()
                    ->live()
                    ->helperText(fn ($state) =>
                        $state ? VacancyStatus::from($state)->description() : null
                    ),

                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Дата публікації')
                    ->seconds(false)
                    ->locale('uk')
                    ->displayFormat('d.m.Y H:i')
                    ->helperText('Час, коли вакансію вперше опублікували.'),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Дата завершення')
                    ->seconds(false)
                    ->locale('uk')
                    ->displayFormat('d.m.Y H:i')
                    ->after('published_at')
                    ->helperText('Після цієї дати вакансія перейде в "Завершено".'),
            ])
            ->columns(2),
    ]);
}
```

---

## 📊 Table (таблиця)

`VacancyResource::table()`:

```php
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')
                ->label('Назва')
                ->searchable()
                ->limit(50),

            Tables\Columns\TextColumn::make('status')
                ->label('Статус')
                ->badge()
                ->color(fn (VacancyStatus $state) => $state->color())
                ->formatStateUsing(fn (VacancyStatus $state) => $state->label()),

            Tables\Columns\TextColumn::make('published_at')
                ->label('Опубліковано')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->placeholder('—'),

            Tables\Columns\TextColumn::make('expires_at')
                ->label('Завершення')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->placeholder('—')
                ->color(fn ($record) =>
                    $record->is_active && $record->hours_left !== null && $record->hours_left < 72
                        ? 'warning' : null
                ),

            Tables\Columns\TextColumn::make('countdown_label')
                ->label('Залишок')
                ->placeholder('—'),
        ])
        ->filters([
            SelectFilter::make('status')
                ->label('Статус')
                ->options(VacancyStatus::options()),

            Filter::make('expiring_soon')
                ->label('Завершуються найближчі 3 дні')
                ->query(fn (Builder $q) => $q->expiringSoon(72)),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),

            Tables\Actions\Action::make('extend_30')
                ->label('Продовжити +30')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record) =>
                    in_array($record->status, [VacancyStatus::Active, VacancyStatus::Expired])
                )
                ->requiresConfirmation()
                ->action(fn ($record) => $record->extend(30))
                ->successNotificationTitle('Вакансію продовжено на 30 днів'),

            Tables\Actions\Action::make('archive')
                ->label('Архівувати')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn ($record) => $record->status !== VacancyStatus::Archived)
                ->requiresConfirmation()
                ->modalDescription('Архівована вакансія повертає 404 за прямим URL. Цю дію можна скасувати лише вручну через зміну статусу.')
                ->action(fn ($record) => $record->archive())
                ->successNotificationTitle('Вакансію архівовано'),
        ])
        ->bulkActions([
            Tables\Actions\BulkAction::make('extend_30_bulk')
                ->label('Продовжити вибрані на 30 днів')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(fn ($records) => $records->each(fn ($r) =>
                    in_array($r->status, [VacancyStatus::Active, VacancyStatus::Expired])
                        ? $r->extend(30) : null
                ))
                ->deselectRecordsAfterCompletion(),

            Tables\Actions\BulkAction::make('archive_bulk')
                ->label('Архівувати вибрані')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn ($records) => $records->each(fn ($r) =>
                    $r->status !== VacancyStatus::Archived ? $r->archive() : null
                ))
                ->deselectRecordsAfterCompletion(),
        ])
        ->defaultSort('expires_at', 'asc');
}
```

---

## ⚠️ Нюанси

1. **`requiresConfirmation()` обов'язково для archive** — це руйнівна дія для SEO.

2. **`visible(fn ($record) => ...)`** — приховує дії, коли вони не мають сенсу (не показуй «Продовжити» для draft).

3. **Не дублюй бізнес-логіку в actions** — викликай методи моделі (`$record->extend(30)`, не `$record->update(['expires_at' => ...])`).

4. **`->live()` на Select status** — щоб `helperText` оновлювався при зміні значення.

5. **Authorization (Policy)** — обов'язково додай `VacancyPolicy` (через `php artisan make:policy VacancyPolicy --model=Vacancy`), щоб роботодавець бачив тільки СВОЇ вакансії. Filament автоматично підхопить.

---

## ✅ Результат

- VacancyResource створено / оновлено.
- Form з валідацією дат.
- Таблиця з бейджами + фільтри.
- Actions: extend_30, archive (single + bulk).
- Перейти до модуля 6 (Stripe webhook — або одразу до 11A якщо Stripe не потрібен).
