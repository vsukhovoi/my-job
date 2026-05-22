# Промпт для Claude Code: ATS (Applicant Tracking System) для My Job

> **Мета документа:** покроковий, модульний промпт для Claude Code, який реалізує систему керування життєвим циклом вакансій (ATS) у проєкті My Job.
>
> **Стек:** Laravel 13, Livewire 3 / Volt, Filament v4, Tailwind CSS, Redis, Docker, Stripe, Nutgram (Telegram), PHPUnit, Laravel Dusk.
>
> **Принцип роботи:** один модуль = один промпт = одне підтвердження. Не переходити до наступного модуля, поки попередній не пройшов тести й не підтверджений розробником.

---

## 📋 Контекст для Claude Code

Ти працюєш у Laravel 13 проєкті **My Job** — український job-board, орієнтований на ринок з референсом на Work.ua. Проєкт уже має:

- Модель `Vacancy` (вакансія)
- Модель `Employer` / `Company` (роботодавець)
- Модель `User` (користувач: і шукач, і роботодавець)
- Адмінку на **Filament v4**
- Сторінки на **Livewire / Volt**
- Інтерфейс — українською мовою
- Платіжну інтеграцію зі **Stripe** (webhook вже частково налаштований)
- Telegram-бота на **Nutgram**

**Твоє завдання** — додати повноцінний ATS-модуль життєвого циклу вакансій: публікація → активність → автоматичне завершення → продовження → архів. Без видалення оголошень (SEO-friendly).

---

## ⚠️ Загальні правила виконання

1. **Один крок — одна відповідь.** Не виконуй декілька модулів за одну ітерацію.
2. **Перед кожним кроком** — покажи план змін: які файли створиш, які зміниш, що видалиш.
3. **Чекай підтвердження** ("OK", "продовжуй", "так") перед застосуванням змін.
4. **Жодних деструктивних дій** (`migrate:fresh`, `php artisan db:wipe`, видалення таблиць) без явного дозволу.
5. **Після кожного модуля** — запусти тести: `php artisan test --filter=<NameTest>`.
6. **Усі коментарі, повідомлення для користувачів, заголовки, кнопки — українською.**
7. **Технічні ідентифікатори** (назви класів, методів, полів БД, ключі translation) — англійською у `snake_case` / `camelCase` / `PascalCase` за конвенцією Laravel.
8. **Перед написанням коду** — перевір, чи існує вже подібна функціональність у проєкті. Не дублюй.
9. **Дотримуйся PSR-12**, типізації параметрів і повернень, `declare(strict_types=1);` де доречно.
10. **Не додавай зовнішніх пакетів** без узгодження. Якщо потрібен пакет — спочатку запропонуй і обґрунтуй.

---

## 🗺️ Карта модулів (виконувати ПОСЛІДОВНО)

| № | Модуль | Залежності | Статус |
|---|--------|------------|--------|
| 1 | Міграція `vacancies`: поля статусу та термінів | — | ⏳ |
| 2 | Модель `Vacancy`: статуси, scopes, accessors | 1 | ⏳ |
| 3 | Enum `VacancyStatus` | 1 | ⏳ |
| 4 | Scheduler: автоматичне маркування expired | 2, 3 | ⏳ |
| 5 | Filament v4: керування статусами та термінами в адмінці | 2, 3 | ⏳ |
| 6 | Stripe Webhook: продовження вакансії після оплати | 2, 3 | ⏳ |
| 7 | Livewire/Volt: лічильник «Залишилось N днів» у реальному часі | 2 | ⏳ |
| 8 | Nutgram: сповіщення за 24 години до закінчення | 2, 3 | ⏳ |
| 9 | SEO-сторінка для expired-вакансій (статус «неактивна») | 2, 3 | ⏳ |
| 10 | Тести (PHPUnit + Dusk) | усі попередні | ⏳ |

---

## МОДУЛЬ 1. Міграція `vacancies`

### Завдання
Додати до таблиці `vacancies` поля для керування життєвим циклом публікації.

### Перед роботою — покажи мені:
1. Структуру наявної таблиці `vacancies` (виклик `php artisan db:show vacancies` або `Schema::getColumnListing`).
2. Чи існують уже поля `status`, `published_at`, `expires_at`. Якщо так — порівняй типи та запропонуй стратегію (доповнити, перейменувати, нічого не робити).

### Чого хочу досягти

```php
Schema::table('vacancies', function (Blueprint $table) {
    $table->timestamp('published_at')->nullable()->after('updated_at');
    $table->timestamp('expires_at')->nullable()->after('published_at');
    $table->string('status', 32)->default('draft')->after('expires_at');

    $table->index(['status', 'expires_at']);   // для scheduler-запиту
    $table->index('published_at');             // для сортування на лістингу
});
```

### Вимоги
- Метод `down()` має акуратно відкочувати **тільки ці зміни** (`dropIndex`, `dropColumn`), не зачіпаючи інших полів.
- Назва міграції: `add_lifecycle_fields_to_vacancies_table`.
- **НЕ виконуй** `php artisan migrate` автоматично — лише створи файл і покажи його повний вміст.

### Очікуваний результат
- Файл `database/migrations/YYYY_MM_DD_HHMMSS_add_lifecycle_fields_to_vacancies_table.php`.
- Звіт: «Міграцію створено. Запустити `php artisan migrate`?»

---

## МОДУЛЬ 2. Модель `Vacancy`

### Завдання
Розширити модель `App\Models\Vacancy` новими `casts`, `scopes`, `accessors`.

### Перед роботою — покажи мені:
1. Поточний вміст файлу `app/Models/Vacancy.php`.
2. Список наявних `$fillable`, `$casts`, scopes — щоб не дублювати.

### Чого хочу досягти

```php
protected $casts = [
    // ... наявні поля
    'published_at' => 'datetime',
    'expires_at'   => 'datetime',
    'status'       => VacancyStatus::class, // enum з модуля 3
];

// Scopes:
public function scopeActive(Builder $q): Builder
{
    return $q->where('status', VacancyStatus::Active)
             ->whereNotNull('published_at')
             ->where(function ($q) {
                 $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
             });
}

public function scopeExpired(Builder $q): Builder { /* ... */ }
public function scopeDraft(Builder $q): Builder   { /* ... */ }
public function scopeArchived(Builder $q): Builder{ /* ... */ }

public function scopeExpiringSoon(Builder $q, int $hours = 24): Builder
{
    return $q->where('status', VacancyStatus::Active)
             ->whereBetween('expires_at', [now(), now()->addHours($hours)]);
}

// Accessors / business methods:
public function getIsActiveAttribute(): bool { /* ... */ }
public function getDaysLeftAttribute(): ?int { /* ... */ }   // ціле число днів до закінчення або null
public function getHoursLeftAttribute(): ?int { /* ... */ }
public function publish(int $days = 30): void { /* status=Active, published_at=now, expires_at=now+days */ }
public function extend(int $days): void       { /* expires_at += days, якщо expired → reactivate */ }
public function archive(): void               { /* status=Archived */ }
public function expire(): void                { /* status=Expired */ }
```

### Вимоги
- Усі публічні методи — з типізацією та PHPDoc українською (короткий опис) / англійською (technical).
- Додай константу `DEFAULT_PUBLICATION_DAYS = 30`.
- Метод `extend()` має коректно працювати, якщо вакансія expired (тоді `expires_at = now() + $days` і `status = Active`).
- Не змінюй `published_at` під час `extend()` — це історичне поле «коли вперше опублікували».

### Очікуваний результат
- Оновлений `app/Models/Vacancy.php`.
- Запустити `php artisan test --filter=VacancyTest` (тести з модуля 10 додасться пізніше).

---

## МОДУЛЬ 3. Enum `VacancyStatus`

### Завдання
Створити PHP 8.1+ backed enum для статусів вакансії.

### Чого хочу досягти

```php
namespace App\Enums;

enum VacancyStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Expired  = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Draft    => 'Чернетка',
            self::Active   => 'Активна',
            self::Expired  => 'Завершена',
            self::Archived => 'Архів',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft    => 'gray',
            self::Active   => 'success',
            self::Expired  => 'warning',
            self::Archived => 'danger',
        };
    }

    public function badgeClass(): string  // Tailwind-класи для фронтенду
    {
        return match($this) {
            self::Draft    => 'bg-gray-100 text-gray-700',
            self::Active   => 'bg-green-100 text-green-700',
            self::Expired  => 'bg-yellow-100 text-yellow-700',
            self::Archived => 'bg-red-100 text-red-700',
        };
    }

    public static function options(): array  // для Filament Select / форм
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
            ->toArray();
    }
}
```

### Очікуваний результат
- Файл `app/Enums/VacancyStatus.php`.
- Швидкий sanity-check: `php artisan tinker --execute="dump(App\Enums\VacancyStatus::Active->label());"`.

---

## МОДУЛЬ 4. Scheduler — автоматичне expired

### Завдання
Налаштувати щогодинне завдання, яке переводить активні вакансії в `expired`, коли `expires_at < now()`.

### Реалізація — два варіанти, обери ОДИН (запитай мене):

**Варіант A: closure у `routes/console.php`** (швидко, мінімум коду):

```php
use App\Models\Vacancy;
use App\Enums\VacancyStatus;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $count = Vacancy::where('status', VacancyStatus::Active)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => VacancyStatus::Expired->value]);

    logger()->info("[Scheduler] Expired vacancies: {$count}");
})->hourly()->name('vacancies:expire')->withoutOverlapping();
```

**Варіант B: окрема команда** (краще для тестування і логування):
- `app/Console/Commands/ExpireVacanciesCommand.php`
- Сигнатура: `vacancies:expire {--dry-run}`
- Реєстрація в `routes/console.php`: `Schedule::command('vacancies:expire')->hourly();`
- Команда має логувати в окремий канал `vacancies` (`config/logging.php`).

### Я хочу варіант B — він тестується через PHPUnit і має `--dry-run`.

### Вимоги
- `withoutOverlapping()` обов'язково (Redis-lock).
- Виведення в консоль: `Expired N vacancies in M ms` (українською: `Завершено N вакансій за M мс`).
- При `--dry-run` — лише виводить кількість, нічого не оновлює.
- Метод `handle()` повертає `int` (Laravel exit code).

### Очікуваний результат
- `app/Console/Commands/ExpireVacanciesCommand.php`
- Оновлений `routes/console.php`
- Тест: `php artisan vacancies:expire --dry-run`

---

## МОДУЛЬ 5. Filament v4 — керування в адмінці

### Завдання
Додати в Filament-ресурс `VacancyResource`:

1. **Поля форми (Form):**
   - `DateTimePicker::make('published_at')` з `seconds(false)`, локалізацією `uk`.
   - `DateTimePicker::make('expires_at')` з валідацією `after_or_equal:published_at`.
   - `Select::make('status')->options(VacancyStatus::options())->required()`.

2. **Колонки таблиці (Table):**
   - `TextColumn::make('status')` з `badge()`, кольором з `VacancyStatus::color()`, лейблом з `label()`.
   - `TextColumn::make('expires_at')->dateTime('d.m.Y H:i')->sortable()`.
   - Додаткова колонка-acessor `days_left` з форматуванням «3 дні» / «завершено».

3. **Фільтри:**
   - `SelectFilter::make('status')->options(VacancyStatus::options())`.
   - `Filter::make('expiring_soon')` — `query(fn ($q) => $q->expiringSoon(72))`, лейбл «Закінчуються за 3 дні».

4. **Actions (для одного запису):**
   - `Action::make('extend_30')` — лейбл «Продовжити на 30 днів», іконка `heroicon-o-arrow-path`, дія: `$record->extend(30)`.
   - `Action::make('archive')` — лейбл «В архів», вимагає `requiresConfirmation()`.

5. **BulkActions (масові):**
   - `BulkAction::make('extend_30_bulk')` — продовжити обрані на 30 днів.
   - `BulkAction::make('archive_bulk')` — архівувати обрані.

### Перед роботою — покажи мені:
- Поточний `app/Filament/Resources/VacancyResource.php` (або еквівалент шляху Filament v4).

### Очікуваний результат
- Оновлений `VacancyResource`.
- Жодної логіки в самому ресурсі — лише виклики методів моделі (`$record->extend(30)`).

---

## МОДУЛЬ 6. Stripe Webhook — оплата = продовження

### Завдання
У вже наявному `StripeWebhookController` (або еквіваленті) додай обробник події `checkout.session.completed`, який:

1. Знаходить вакансію за `metadata.vacancy_id` сесії Stripe.
2. Зчитує кількість днів з `metadata.days` (15 / 30 / 90 — value із продукту).
3. Викликає `$vacancy->extend((int) $metadata['days'])` (тобто `expires_at += days`, статус → `Active`).
4. Зберігає платіж у таблиці `payments` (якщо вона існує — спочатку перевір!).
5. Тригерить подію `VacancyExtended` (Laravel event) — її слухатимуть Nutgram-нотифікації.

### Перед роботою — покажи мені:
- Чи існує контролер вебхуків Stripe? Шлях?
- Чи існує таблиця `payments`? Якщо ні — НЕ створюй її в цьому модулі. Просто залогуй платіж у `storage/logs/payments.log` і додай TODO-коментар.

### Вимоги
- Verify Stripe signature (через `Webhook::constructEvent()` з пакета `stripe/stripe-php`).
- Idempotency: якщо подія з таким `event_id` уже оброблена — пропусти. Використай таблицю `stripe_processed_events` або кеш Redis з ключем `stripe:event:{id}` на 24 години.
- Усі помилки — у Sentry / Log, але **не повертай 500 Stripe** при бізнес-помилках (інакше Stripe ретраїтиме). Лог + 200 OK.
- Подія `App\Events\VacancyExtended` з `public Vacancy $vacancy` і `public int $days`.

### Очікуваний результат
- Оновлений `StripeWebhookController`.
- Файл `app/Events/VacancyExtended.php`.

---

## МОДУЛЬ 7. Livewire/Volt — лічильник у реальному часі

### Завдання
Створити Livewire/Volt компонент, який показує користувачеві:

```
┌─────────────────────────────────┐
│  🟢 Активна                     │
│  Залишилось: 3 дні 14 годин     │
│  [ Продовжити публікацію ]      │
└─────────────────────────────────┘
```

### Реалізація
- Компонент `app/Livewire/VacancyCountdown.php` (або Volt-файл `resources/views/livewire/vacancy-countdown.blade.php`).
- Параметри: `Vacancy $vacancy`.
- Оновлення кожні 60 секунд через `wire:poll.60s` — НЕ кожну секунду (зайве навантаження).
- Логіка форматування — у `Vacancy::getDaysLeftAttribute()` і допоміжному методі `getCountdownLabel(): string`:
  - `> 1 день` → «Залишилось 5 днів»
  - `1 день` → «Залишилось 1 день»
  - `< 24 год` → «Залишилось 14 годин»
  - `< 1 год` → «Залишилось 32 хвилини»
  - `expired` → «Публікацію завершено» + кнопка продовження.

### Важливо
- Українська плюралізація: 1 день / 2-4 дні / 5+ днів. Використай хелпер або `trans_choice()`.
- Кнопка «Продовжити» веде на сторінку оплати Stripe (поки що — заглушка `route('vacancies.extend', $vacancy)`).

### Очікуваний результат
- Компонент + Blade-шаблон з Tailwind.
- Інтеграція в сторінку перегляду вакансії роботодавця.

---

## МОДУЛЬ 8. Nutgram — сповіщення в Telegram

### Завдання
За 24 години до `expires_at` бот надсилає роботодавцю повідомлення:

```
⏰ Вакансія «PHP Developer» завершиться завтра о 18:30.

[ Продовжити на 30 днів — 200 грн ]
[ Архівувати ]
[ Не нагадувати ]
```

### Реалізація
1. Команда `app/Console/Commands/NotifyExpiringVacanciesCommand.php`:
   - Сигнатура: `vacancies:notify-expiring {--hours=24}`.
   - Знаходить `Vacancy::expiringSoon($hours)` де ще не надсилали сповіщення (поле `expiry_notification_sent_at` — додай у міграції модуля 1 або окремою!).
   - Для кожної: відправляє повідомлення через Nutgram, оновлює `expiry_notification_sent_at = now()`.

2. Реєстрація в scheduler: `Schedule::command('vacancies:notify-expiring')->hourly();`.

3. Inline-кнопки Telegram з callback_data:
   - `vacancy:extend:{id}` — переводить на оплату.
   - `vacancy:archive:{id}` — миттєво архівує.
   - `vacancy:mute:{id}` — додає в `notification_muted_vacancies` (опційно).

### Перед роботою — запитай мене:
- Чи є у моделі `Employer` / `User` поле `telegram_chat_id`?
- Чи зареєстрований Nutgram-бот у `app/Providers/NutgramServiceProvider.php`?

### Очікуваний результат
- Команда + handler-и callback'ів у Nutgram-роутах.
- Міграція для поля `expiry_notification_sent_at` у `vacancies`.

---

## МОДУЛЬ 9. SEO-сторінка для expired-вакансій

### Завдання
Expired-вакансії **залишаються доступними за прямим URL**, але:

1. Показують банер «Ця вакансія неактивна. Перегляньте схожі активні вакансії →».
2. Мають мета-тег `<meta name="robots" content="noindex, follow">` — щоб Google не індексував, але передавав вагу за посиланнями.
3. Показують секцію «Схожі активні вакансії» (3-5 шт. з тієї ж професії / міста).
4. У `Schema.org` JSON-LD статус — `JobPosting` з `validThrough` = `expires_at` (Google обробить як expired сам).

### Реалізація
- Оновити `VacancyController@show` (або Volt-сторінку):
  - Якщо `$vacancy->status === Expired` → передати у view `$isExpired = true`.
  - Якщо `Archived` → `abort(404)` (повністю прибрати з пошуку).
- Blade-компонент `<x-vacancy.expired-banner :vacancy="$vacancy" />`.
- Хелпер `App\Services\SimilarVacanciesService::find(Vacancy $v, int $limit = 5)`.

### Очікуваний результат
- Оновлений контролер + view + новий компонент.

---

## МОДУЛЬ 10. Тести

### PHPUnit (Feature + Unit)

```php
// tests/Unit/VacancyStatusTest.php
test('label returns Ukrainian translation', ...);
test('options returns array for Filament select', ...);

// tests/Unit/VacancyTest.php
test('publish sets status, published_at, expires_at', ...);
test('extend adds days to expires_at', ...);
test('extend reactivates expired vacancy', ...);
test('days_left attribute returns correct integer', ...);
test('active scope filters correctly', ...);
test('expiring_soon scope returns vacancies in window', ...);

// tests/Feature/Console/ExpireVacanciesCommandTest.php
test('command marks expired vacancies as expired', ...);
test('command does not touch active vacancies', ...);
test('dry-run mode does not modify database', ...);

// tests/Feature/Stripe/WebhookExtendsVacancyTest.php
test('checkout.session.completed extends vacancy by metadata days', ...);
test('duplicate event id is ignored (idempotency)', ...);
test('invalid signature returns 400', ...);

// tests/Feature/Filament/VacancyResourceTest.php (Livewire::test для Filament)
test('extend_30 action adds 30 days to expires_at', ...);
test('archive action sets status to archived', ...);
test('bulk archive works on multiple records', ...);
```

### Laravel Dusk (E2E)

```php
// tests/Browser/VacancyCountdownTest.php
test('countdown shows "Залишилось 3 дні" for vacancy expiring in 3 days', ...);
test('expired vacancy shows extension button', ...);

// tests/Browser/ExpiredVacancyPageTest.php
test('expired vacancy page shows banner and noindex meta', ...);
test('archived vacancy returns 404', ...);
```

### Вимоги
- Усі тести — у відповідних теках, з правильними неймспейсами.
- Використовуй `RefreshDatabase` або `DatabaseTransactions`.
- Фабрики (`VacancyFactory`) повинні мати стани: `active()`, `expired()`, `expiringSoon()`, `draft()`, `archived()`.
- Запусти **усі** після завершення: `php artisan test` + `php artisan dusk` (якщо Dusk налаштовано).

---

## ✅ Чек-лист завершення

Після виконання всіх 10 модулів перевір:

- [ ] `php artisan migrate:fresh --seed` проходить без помилок
- [ ] `php artisan test` — усі тести зелені
- [ ] `php artisan vacancies:expire --dry-run` — працює
- [ ] `php artisan vacancies:notify-expiring --hours=24` — працює
- [ ] У Filament відображається бейдж статусу з правильним кольором
- [ ] BulkAction «Продовжити на 30 днів» працює
- [ ] Stripe webhook у Stripe CLI (`stripe trigger checkout.session.completed`) продовжує вакансію
- [ ] Livewire-лічильник оновлюється раз на хвилину
- [ ] Expired-вакансія має `<meta name="robots" content="noindex, follow">`
- [ ] Archived-вакансія повертає 404
- [ ] Telegram-бот надсилає сповіщення за 24 години (тестово через `--hours=999`)

---

## 📚 Корисні нагадування

- **Часові зони:** усі дати — у UTC у БД, конвертуй у `Europe/Kyiv` тільки для відображення (`config('app.timezone_display')`).
- **Локалізація:** Ukrainian-only. Якщо колись буде багатомовність — переноси рядки в `lang/uk/vacancies.php`.
- **Кешування:** не кешуй `days_left` — воно динамічне. Кешуй лише списки вакансій (`active`, `expiring_soon`) на 5-10 хвилин з тегом `vacancies`.
- **Безпека:** усі дії над вакансією (`extend`, `archive`) мають проходити через Policy (`VacancyPolicy@update`).
- **Логування:** події життєвого циклу — у канал `vacancies` (`storage/logs/vacancies.log`), щоб не змішувати з основним логом.

---

## 🚦 Стратегія відкату

Якщо щось ламається на будь-якому модулі:

1. **`git status`** — переглянь зміни.
2. **`git diff`** — точкові правки CSS / шаблонів.
3. **`git restore <file>`** — відкат окремого файлу.
4. **`git revert HEAD`** — повний відкат останнього коміту, якщо вже закомічено.
5. **Не виконуй `git reset --hard`** без явного підтвердження — це знищує uncommitted-зміни без можливості відновлення.

Кожен модуль — окремий коміт із префіксом `feat(ats):`, `test(ats):`, `chore(ats):`.

---

**Початок роботи:** надішли мені **МОДУЛЬ 1** (план + питання щодо наявної структури `vacancies`). Чекаю на твою відповідь перед застосуванням будь-яких змін.
