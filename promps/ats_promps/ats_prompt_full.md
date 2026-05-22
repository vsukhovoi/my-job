# ATS для My Job — Повний промпт-комплект (one-file edition)

> Цей файл — об'єднання майстер-документа і всіх 10 модулів в один.
> Зручно, якщо хочеш бачити весь контекст одразу. Для роботи з Claude Code
> рекомендую використовувати окремі файли модулів — так легше дотримуватись
> confirmation gate logic.

---

# ATS для My Job — Повний комплект промптів

Це набір промптів для Claude Code для реалізації Applicant Tracking System
(життєвий цикл вакансії: published_at, expires_at, status) у Laravel-проєкті My Job.

## Як використовувати

1. **Прочитай `ats_prompt.md`** — це майстер-документ, він описує:
   - Стратегію та принципи (модульність, confirmation gates)
   - Залежності між модулями (1 → 2 → 3 → ... → 10)
   - Загальні правила виконання

2. **Іди по модулях по порядку**, передаючи їх Claude Code один за одним:
   - Скопіюй вміст `modules/01_migration.md` у нову розмову з Claude Code
   - Дочекайся, поки Claude Code завершить і запитає підтвердження
   - Перевір результат, дай OK
   - Передай `modules/02_model.md`
   - І так далі

3. **НЕ передавай усі модулі одразу.** Це зруйнує конфірмаційну логіку,
   яка є основним захистом від помилок.

## Карта файлів

```
ats_prompts/
├── README.md                     ← ти тут
├── ats_prompt.md                 ← майстер-документ
└── modules/
    ├── 01_migration.md           ← розгорнутий — критично для основи
    ├── 02_model.md               ← розгорнутий — серцевина бізнес-логіки
    ├── 03_enum.md                ← компактний
    ├── 04_scheduler.md           ← розгорнутий — race conditions
    ├── 05_filament.md            ← компактний
    ├── 06_stripe_webhook.md      ← розгорнутий — гроші, безпека
    ├── 07_livewire_countdown.md  ← розгорнутий — UX + плюралізація
    ├── 08_nutgram.md             ← розгорнутий — Telegram-інтеграція
    ├── 09_seo_expired.md         ← компактний
    └── 10_tests.md               ← розгорнутий — фундамент якості
```

## Послідовність виконання

| # | Модуль | Залежить від | Розмір |
|---|--------|--------------|--------|
| 1 | Migration | — | ~9 KB |
| 2 | Model | 1 | ~18 KB |
| 3 | Enum | — | ~4 KB |
| 4 | Scheduler | 2, 3 | ~16 KB |
| 5 | Filament | 2, 3 | ~9 KB |
| 6 | Stripe Webhook | 2, 3, 4 | ~25 KB |
| 7 | Livewire Countdown | 2, 3 | ~22 KB |
| 8 | Nutgram | 2, 6 | ~25 KB |
| 9 | SEO Expired | 2, 3 | ~8 KB |
| 10 | Tests | 1-9 | ~35 KB |

Послідовність 1 → 3 → 2 → 4 → 5 → 6 → 7 → 8 → 9 → 10 теж працює:
3 (enum) можна зробити раніше за 2 (model), бо модель залежить від enum,
а не навпаки. Я в README залишив природний порядок, але якщо хочеш —
переставляй 2 і 3 місцями.

## Контрольні точки

Після кожного модуля Claude Code повинен:
1. Звітувати, які файли створено/змінено
2. Запитувати підтвердження перед `migrate`, перед `npm run build`,
   перед будь-якими руйнівними операціями
3. Чекати твого OK перед переходом до наступного модуля

Якщо Claude Code робить це БЕЗ confirmation — зупини, переконайся, що
скопіював правильний модуль.

## Recovery

Якщо щось зламалось:
- `git diff` — побачити зміни
- `git checkout .` — відкатити нескомічене
- `git revert <commit>` — відкатити уже закомічене

Не борися з поганим результатом 5+ ітерацій. Швидше відкотити і дати
ще раз чітко сформульований модуль.
-e 

---

# ЧАСТИНА 1: МАЙСТЕР-ДОКУМЕНТ


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
-e 

---

# ЧАСТИНА 2: МОДУЛІ


-e 

---


# МОДУЛЬ 1 (розгорнутий). Міграція `vacancies` — поля життєвого циклу

## 🎯 Мета модуля
Додати до таблиці `vacancies` три поля та два індекси, які стануть основою всіх наступних модулів. Без цього кроку нічого далі не запрацює.

---

## 🔍 КРОК 1.1. Розвідка наявної структури

**Перш ніж писати міграцію — обов'язково виведи:**

```bash
php artisan db:show vacancies
```

Або, якщо команда недоступна:

```bash
php artisan tinker --execute="dump(Schema::getColumnListing('vacancies'));"
php artisan tinker --execute="dump(DB::select('SHOW CREATE TABLE vacancies'));"
```

Скопіюй повний вивід у відповідь. Я хочу побачити:
- Усі колонки з типами
- Наявні індекси
- Foreign keys (якщо є)
- Чи існують поля `status`, `published_at`, `expires_at`, `expiry_notification_sent_at`

---

## ⚖️ КРОК 1.2. Стратегія залежно від результату розвідки

| Що знайшов у БД | Що робити |
|-----------------|-----------|
| Жодного з потрібних полів немає | Створюй міграцію `add_lifecycle_fields_to_vacancies_table` (нижче) |
| Є `status` як `enum`/`string` з іншими значеннями | **СТОП.** Не зачіпай — спершу обговори зі мною стратегію міграції даних |
| Є `published_at` як `boolean` (`is_published`) | Запропонуй окрему міграцію перенесення: `boolean → timestamp`, з backfill `published_at = created_at WHERE is_published = 1` |
| Є `expires_at` з іншим типом | Покажи мені — обговоримо |
| Усі три поля вже є з правильними типами | Перевір індекси (`status` + `expires_at`) і пропусти модуль |

**НЕ роби** жодних руйнівних змін без явного OK.

---

## 📝 КРОК 1.3. Файл міграції (якщо полів немає)

Створи файл:

```
database/migrations/YYYY_MM_DD_HHMMSS_add_lifecycle_fields_to_vacancies_table.php
```

Повний вміст:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            // Час першої публікації — не змінюється під час продовження
            $table->timestamp('published_at')->nullable()->after('updated_at');

            // Час, коли вакансія перестане бути активною
            $table->timestamp('expires_at')->nullable()->after('published_at');

            // Поточний стан життєвого циклу
            $table->string('status', 32)->default('draft')->after('expires_at');

            // Прапорець, чи надсилали сповіщення «скоро завершиться»
            // (використовується модулем 8 — Nutgram)
            $table->timestamp('expiry_notification_sent_at')->nullable()->after('status');

            // Індекс для scheduler-запиту (модуль 4):
            // SELECT ... WHERE status = 'active' AND expires_at < NOW()
            $table->index(['status', 'expires_at'], 'vacancies_status_expires_idx');

            // Індекс для лістингу та сортування за датою публікації
            $table->index('published_at', 'vacancies_published_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            // Видаляємо індекси ПЕРЕД колонками — інакше MySQL/MariaDB може скаржитись
            $table->dropIndex('vacancies_status_expires_idx');
            $table->dropIndex('vacancies_published_at_idx');

            $table->dropColumn([
                'published_at',
                'expires_at',
                'status',
                'expiry_notification_sent_at',
            ]);
        });
    }
};
```

---

## ⚠️ КРОК 1.4. Критичні нюанси

1. **Порядок у `down()`**: спочатку `dropIndex`, тільки потім `dropColumn`. Інакше MySQL 5.7 / MariaDB видасть `Errno 1553`.

2. **Іменовані індекси**: я навмисно дав явні імена (`vacancies_status_expires_idx`). Без цього Laravel генерує довге автоім'я, і `dropIndex` може не знайти його при відкаті, якщо назва колонки змінилася.

3. **`->after('updated_at')`**: працює тільки в MySQL/MariaDB. Якщо проєкт на PostgreSQL — прибери ці виклики (Postgres ігнорує `after`, але деякі версії можуть кинути попередження). Спершу перевір `config('database.default')`.

4. **`status` як `string(32)`**, не `enum`: Laravel-enum-тип на рівні БД — це біль при міграціях (додавання нового case вимагає `ALTER TABLE`). Тип `string` + PHP enum (модуль 3) — стандартна best-practice 2025.

5. **`expiry_notification_sent_at` додано **тут**, а не в окремій міграції модуля 8** — щоб не плодити міграції, бо це теж поле життєвого циклу.

6. **Тип `timestamp` (не `dateTime`)**: `timestamp` зберігається в UTC і автоматично конвертується. `dateTime` — naive, без TZ. Для життєвого циклу та scheduler-запитів `timestamp` правильніший.

---

## 🧪 КРОК 1.5. Backfill для існуючих вакансій (якщо БД не порожня)

Якщо в `vacancies` уже є записи — додай **окрему міграцію** (НЕ змішуй зі схемою!):

```
database/migrations/YYYY_MM_DD_HHMMSS_backfill_vacancy_lifecycle_data.php
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Усім наявним вакансіям без статусу — даємо 'active' і термін +30 днів від created_at
        // (припущення: усе, що є в БД зараз, було активним)
        DB::table('vacancies')
            ->whereNull('published_at')
            ->update([
                'published_at' => DB::raw('created_at'),
                'expires_at'   => DB::raw('DATE_ADD(created_at, INTERVAL 30 DAY)'),
                'status'       => 'active',
            ]);

        // Ті, у кого expires_at вже минув — позначаємо як expired
        DB::table('vacancies')
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    public function down(): void
    {
        // Backfill незворотній — лишаємо порожнім
    }
};
```

**УВАГА:** запит `DATE_ADD(..., INTERVAL 30 DAY)` — MySQL/MariaDB. Для Postgres: `created_at + INTERVAL '30 days'`.

**Перш ніж писати backfill — запитай мене:**
- Скільки записів у `vacancies`? (`SELECT COUNT(*) FROM vacancies`)
- Який сенс мали ці вакансії раніше — усі активні, чи частина чернетки?
- Можливо, частину треба позначити `archived`, а не `active`?

---

## ✅ Очікуваний результат модуля

1. Файл `database/migrations/..._add_lifecycle_fields_to_vacancies_table.php` створено.
2. (Опційно) Файл `..._backfill_vacancy_lifecycle_data.php` створено.
3. Звіт мені:
   ```
   Створено міграцію: add_lifecycle_fields_to_vacancies_table
   Поля: published_at, expires_at, status, expiry_notification_sent_at
   Індекси: vacancies_status_expires_idx, vacancies_published_at_idx

   Запустити "php artisan migrate"? (так/ні)
   ```

**НЕ запускай `migrate` без мого підтвердження.**

---

## 🚨 Чого НЕ робити в цьому модулі

- ❌ Не оновлюй модель `Vacancy` — це модуль 2.
- ❌ Не створюй enum `VacancyStatus` — це модуль 3.
- ❌ Не запускай `migrate:fresh` — це знищить продакшен-дані.
- ❌ Не додавай foreign keys на цьому етапі — їх немає в специфікації.
- ❌ Не пиши тести — це модуль 10.

Завершуй цей модуль і чекай мого підтвердження для переходу до модуля 2.
-e 

---


# МОДУЛЬ 2 (розгорнутий). Модель `Vacancy` — статуси, scopes, бізнес-методи

## 🎯 Мета модуля
Перетворити `App\Models\Vacancy` на повноцінний агрегат життєвого циклу: декларативні стани, безпечні переходи між ними, обчислювані атрибути для UI, scopes для запитів. Уся логіка статусів живе тут — і ніде більше.

**Передумова:** модуль 1 виконано та `php artisan migrate` запущено.

---

## 🔍 КРОК 2.1. Розвідка наявної моделі

Виведи мені повний поточний вміст:

```bash
cat app/Models/Vacancy.php
```

Я хочу побачити:
- `$fillable` — щоб знати, чи додавати нові поля туди
- `$casts` — щоб не дублювати
- Наявні scopes — раптом є `scopePublished` / `scopeActive` зі старою логікою
- Релейшни (`belongsTo Employer`, `hasMany Application` тощо) — щоб не зламати
- Імпорти на початку файлу

---

## 📐 КРОК 2.2. Структура змін

Я додаю в модель **п'ять блоків**:

1. Доповнення `$fillable` та `$casts`
2. Константи (`DEFAULT_PUBLICATION_DAYS`)
3. Scopes (5 штук)
4. Computed accessors (`days_left`, `hours_left`, `is_active`, `countdown_label`)
5. State-transition methods (`publish`, `extend`, `archive`, `expire`, `markNotificationSent`)

**Усе інше в моделі — не чіпай.**

---

## 📝 КРОК 2.3. Повна реалізація

### 2.3.1. Імпорти (додай угорі файлу)

```php
use App\Enums\VacancyStatus;            // створимо в модулі 3
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
```

### 2.3.2. Константа та `$fillable` / `$casts`

```php
class Vacancy extends Model
{
    /**
     * Скільки днів за замовчуванням триває одна публікація.
     */
    public const DEFAULT_PUBLICATION_DAYS = 30;

    protected $fillable = [
        // ... наявні поля — НЕ ВИДАЛЯЙ
        'published_at',
        'expires_at',
        'status',
        'expiry_notification_sent_at',
    ];

    protected $casts = [
        // ... наявні касти
        'published_at'                => 'datetime',
        'expires_at'                  => 'datetime',
        'expiry_notification_sent_at' => 'datetime',
        'status'                      => VacancyStatus::class,
    ];
```

> **Важливо:** `'status' => VacancyStatus::class` працює лише з модуля 3. Якщо створюєш модель ДО enum — тимчасово залиш `'status' => 'string'` і повернись сюди після модуля 3.

### 2.3.3. Scopes

```php
    /**
     * Активні вакансії: status=active, опубліковані, ще не expired.
     * Використовується на публічному лістингу.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', VacancyStatus::Active)
            ->whereNotNull('published_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Завершені — час вийшов, але ще не архівовані.
     * Залишаються доступні за прямим URL для SEO (модуль 9).
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Expired);
    }

    /**
     * Чернетки — не показуються нікому, крім автора.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Draft);
    }

    /**
     * Архів — повністю прибрані з пошуку та з прямих URL (404).
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Archived);
    }

    /**
     * Активні вакансії, що завершаться найближчим часом.
     * Використовується модулем 8 (Nutgram-нотифікації).
     *
     * @param  int  $hours  у скільки годин від зараз шукаємо expires_at
     */
    public function scopeExpiringSoon(Builder $query, int $hours = 24): Builder
    {
        return $query
            ->where('status', VacancyStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours($hours)]);
    }

    /**
     * Активні + ще не надсилали сповіщення «скоро завершиться».
     * Захищає від дублікатів у Nutgram-команді.
     */
    public function scopePendingExpiryNotification(Builder $query, int $hours = 24): Builder
    {
        return $query->expiringSoon($hours)
            ->whereNull('expiry_notification_sent_at');
    }
```

### 2.3.4. Computed accessors (Laravel 9+ синтаксис)

```php
    /**
     * Чи вакансія активна ЗАРАЗ (з урахуванням expires_at).
     * Не дорівнює `$vacancy->status === VacancyStatus::Active`,
     * бо статус оновлюється раз на годину scheduler-ом.
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(fn () =>
            $this->status === VacancyStatus::Active
            && $this->published_at !== null
            && ($this->expires_at === null || $this->expires_at->isFuture())
        );
    }

    /**
     * Скільки повних днів залишилось.
     * null — якщо вакансія не активна або немає expires_at.
     * 0 — менше доби, але ще не expired.
     */
    protected function daysLeft(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->is_active || ! $this->expires_at) {
                return null;
            }

            $diff = (int) now()->diffInDays($this->expires_at, absolute: false);

            return max($diff, 0);
        });
    }

    /**
     * Скільки повних годин залишилось (для відображення < 1 дня).
     */
    protected function hoursLeft(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->is_active || ! $this->expires_at) {
                return null;
            }

            return max((int) now()->diffInHours($this->expires_at, absolute: false), 0);
        });
    }

    /**
     * Користувацький лейбл «Залишилось N днів» з правильною українською плюралізацією.
     * Використовується Livewire-компонентом (модуль 7).
     */
    protected function countdownLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status === VacancyStatus::Expired) {
                return 'Публікацію завершено';
            }

            if ($this->status === VacancyStatus::Archived) {
                return 'В архіві';
            }

            if ($this->status === VacancyStatus::Draft) {
                return 'Чернетка';
            }

            if (! $this->expires_at) {
                return 'Безстрокова публікація';
            }

            $hoursLeft = $this->hours_left ?? 0;

            // Менше години
            if ($hoursLeft < 1) {
                $minutes = max((int) now()->diffInMinutes($this->expires_at, absolute: false), 0);
                return "Залишилось {$minutes} " . self::pluralizeUk($minutes, 'хвилина', 'хвилини', 'хвилин');
            }

            // Менше доби
            if ($hoursLeft < 24) {
                return "Залишилось {$hoursLeft} " . self::pluralizeUk($hoursLeft, 'година', 'години', 'годин');
            }

            $days = $this->days_left;
            return "Залишилось {$days} " . self::pluralizeUk($days, 'день', 'дні', 'днів');
        });
    }

    /**
     * Українська плюралізація для чисел: 1 / 2-4 / 5+.
     * Працює і для 11-14 (там «днів», не «дні»).
     */
    private static function pluralizeUk(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }
```

### 2.3.5. State transitions

```php
    /**
     * Опублікувати вакансію вперше: status=Active, published_at=now, expires_at=now+$days.
     * Якщо вакансія вже Active — НЕ змінюємо published_at (це історичне поле).
     *
     * @throws \DomainException якщо вакансія Archived — її не можна публікувати без розархівації
     */
    public function publish(int $days = self::DEFAULT_PUBLICATION_DAYS): void
    {
        if ($this->status === VacancyStatus::Archived) {
            throw new \DomainException(
                "Вакансію #{$this->id} архівовано. Спочатку відновіть її."
            );
        }

        $this->forceFill([
            'status'       => VacancyStatus::Active,
            'published_at' => $this->published_at ?? now(),
            'expires_at'   => now()->addDays($days),
            // Скидаємо прапорець нотифікації — щоб новий цикл міг знову нагадати
            'expiry_notification_sent_at' => null,
        ])->save();
    }

    /**
     * Продовжити публікацію на $days днів.
     * Логіка:
     *   - якщо Active: expires_at += $days
     *   - якщо Expired: status=Active, expires_at = now+$days (не +до старого expires_at,
     *     бо клієнт платить ЗА ПЕРІОД, а не за повернення в минуле)
     *   - якщо Draft / Archived: кидаємо виняток
     *
     * Не оновлює published_at — це історичне поле.
     */
    public function extend(int $days): void
    {
        if (! in_array($this->status, [VacancyStatus::Active, VacancyStatus::Expired], true)) {
            throw new \DomainException(
                "Вакансію #{$this->id} не можна продовжити зі статусу {$this->status->value}."
            );
        }

        $newExpiresAt = $this->status === VacancyStatus::Expired
            ? now()->addDays($days)
            : ($this->expires_at ?? now())->addDays($days);

        $this->forceFill([
            'status'                      => VacancyStatus::Active,
            'expires_at'                  => $newExpiresAt,
            'expiry_notification_sent_at' => null, // новий цикл — нове нагадування
        ])->save();
    }

    /**
     * Архівувати вакансію — повністю прибрати з пошуку та з прямих URL (404).
     * Використовуй коли вакансія більше не актуальна назавжди.
     */
    public function archive(): void
    {
        $this->forceFill(['status' => VacancyStatus::Archived])->save();
    }

    /**
     * Позначити як завершену (виконує scheduler автоматично).
     * Залишається доступна за URL для SEO (модуль 9).
     */
    public function expire(): void
    {
        $this->forceFill(['status' => VacancyStatus::Expired])->save();
    }

    /**
     * Позначити, що сповіщення «скоро завершиться» було надіслане.
     * Викликається з модуля 8 (Nutgram).
     */
    public function markExpiryNotificationSent(): void
    {
        $this->forceFill(['expiry_notification_sent_at' => now()])->save();
    }
}
```

---

## ⚠️ Критичні нюанси

### 1. `forceFill` замість `update`
Я навмисно використовую `$this->forceFill([...])->save()` замість `$this->update([...])`. Чому:

- `update()` запускає observers і events, які можуть викликати каскад (наприклад, `Saving::class` логує зміни → той логер викликає Telegram-нотифікацію → ...).
- `forceFill` дає чіткий контроль: лише ці поля, без обходу `$fillable`.
- Якщо в проєкті є observer на `Vacancy` — обговоримо, чи має він спрацьовувати на life-cycle переходах.

### 2. Чому `is_active` ≠ `status === Active`
Між запусками scheduler-а (раз на годину) у БД лежать вакансії зі статусом `Active`, але `expires_at` уже в минулому. Користувач не повинен бачити їх як активні.

`is_active` робить **точну** перевірку «прямо зараз»; `scopeActive()` робить те саме на рівні БД. **Завжди** використовуй `$vacancy->is_active`, а не `$vacancy->status === VacancyStatus::Active`, у фронтенді.

### 3. Українська плюралізація — окремий метод
Не перенось у глобальний хелпер чи Service до того, як побачиш, що вона потрібна в 3+ місцях. Поки що — приватний static у моделі. Винесемо в `App\Support\UkrainianPlural` лише коли з'явиться 3-тє використання.

**Тестові випадки** для `pluralizeUk()`:
| n | one | few | many | Очікуване |
|---|-----|-----|------|-----------|
| 1 | день | дні | днів | день |
| 2 | день | дні | днів | дні |
| 5 | день | дні | днів | днів |
| 11 | день | дні | днів | днів *(не «дні»!)* |
| 21 | день | дні | днів | день |
| 22 | день | дні | днів | дні |
| 111 | день | дні | днів | днів |
| 121 | день | дні | днів | день |

**Покрий усі вісім кейсів у `tests/Unit/UkrainianPluralTest.php`** (модуль 10).

### 4. `extend()` для expired-вакансії
Реальний кейс: користувач платить за продовження через тиждень після того, як вакансія expired. Інтуїтивно очікується «вакансія знову активна на 30 днів від СЬОГОДНІ», а не «активна 30 днів від моменту експайру» (бо клієнт платить за актуальний період, а не за минулий).

Зафіксовано у тестах модуля 10 — `test('extend reactivates expired vacancy from now')`.

### 5. Не використовуй `Carbon::diffInDays()` без `absolute: false`
За замовчуванням Carbon повертає **абсолютне** значення (3 дні минуло І 3 дні в майбутньому повертають однакові 3). У нашому контексті expires_at буде в минулому → абсолютне значення дасть позитивний `days_left` — БАГ.

`absolute: false` повертає від'ємне число для минулих дат, а ми обрізаємо `max(..., 0)`.

### 6. Не використовуй observers для зміни статусів
Спокуса написати `VacancyObserver@saving` з логікою «якщо expires_at < now, то status = expired». **Не треба.** Це призведе до:
- Нескінченних циклів (saving → save → saving)
- Несумісності з `forceFill` методів `expire()` / `extend()`
- Складної дебажки

Усі переходи — лише через явні методи моделі. Scheduler — теж явний.

---

## ✅ Очікуваний результат модуля

1. Файл `app/Models/Vacancy.php` оновлено: усі п'ять блоків додано.
2. Швидка перевірка в tinker:

```bash
php artisan tinker
```

```php
// Створити чернетку
$v = Vacancy::factory()->create(['status' => 'draft']);

// Опублікувати на 30 днів
$v->publish(30);
dump($v->status, $v->published_at, $v->expires_at);

// Перевірити лічильник
dump($v->countdown_label);  // "Залишилось 30 днів"

// Продовжити
$v->extend(15);
dump($v->expires_at);  // +45 днів від сьогодні

// Перевірити плюралізацію
$v->expires_at = now()->addDays(2);
$v->save();
dump($v->countdown_label);  // "Залишилось 2 дні" — НЕ "днів"!

$v->expires_at = now()->addDays(11);
$v->save();
dump($v->countdown_label);  // "Залишилось 11 днів" — НЕ "дні"!
```

3. Звіт мені:
   ```
   Модель Vacancy оновлено.
   Додано: 5 scopes, 4 accessors, 5 state-методів, 1 константа.
   Перевірка через tinker пройшла. Перейти до модуля 3 (VacancyStatus enum)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не пиши тести — модуль 10.
- ❌ Не створюй Filament-ресурс — модуль 5.
- ❌ Не торкайся `VacancyController` чи Volt-сторінок — модулі 7, 9.
- ❌ Не додавай Policy в цьому модулі — окремо.
- ❌ Не додавай scope `published()` як alias до `active()` — їх семантика різна, плутанина.
-e 

---


# МОДУЛЬ 3. Enum `VacancyStatus`

## 🎯 Мета
PHP 8.1+ backed enum для статусів вакансії з лейблами, кольорами для Filament, Tailwind-класами і helper для Filament Select.

**Передумова:** модулі 1–2 виконано.

---

## 📂 Реалізація

`app/Enums/VacancyStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum VacancyStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Expired  = 'expired';
    case Archived = 'archived';

    /**
     * Локалізований лейбл для UI.
     */
    public function label(): string
    {
        return match($this) {
            self::Draft    => 'Чернетка',
            self::Active   => 'Активна',
            self::Expired  => 'Завершена',
            self::Archived => 'Архів',
        };
    }

    /**
     * Колір бейджа для Filament.
     */
    public function color(): string
    {
        return match($this) {
            self::Draft    => 'gray',
            self::Active   => 'success',
            self::Expired  => 'warning',
            self::Archived => 'danger',
        };
    }

    /**
     * Tailwind-класи для бейджа на фронтенді.
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::Draft    => 'bg-gray-100 text-gray-700',
            self::Active   => 'bg-green-100 text-green-700',
            self::Expired  => 'bg-yellow-100 text-yellow-700',
            self::Archived => 'bg-red-100 text-red-700',
        };
    }

    /**
     * Опис для адмінів — пояснює, що означає кожен статус.
     */
    public function description(): string
    {
        return match($this) {
            self::Draft    => 'Не опубліковано — бачить лише автор.',
            self::Active   => 'Активна публікація на сайті.',
            self::Expired  => 'Час публікації вийшов, доступна за прямим URL для SEO.',
            self::Archived => 'Знята з пошуку, повертає 404 за прямим URL.',
        };
    }

    /**
     * Масив для Filament Select / Form options.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
            ->toArray();
    }

    /**
     * Тільки публічно видимі статуси (для лістингів і пошуку).
     */
    public static function publicCases(): array
    {
        return [self::Active, self::Expired];
    }
}
```

---

## ⚠️ Нюанси

1. **`string` як backing type, а не `int`** — потім простіше дивитись у БД (`status = 'active'` зрозуміліше за `status = 2`).

2. **Не змінюй порядок case'ів** після того, як вони в БД. Якщо це enum БД-рівня (не наш випадок) — зміна порядку = міграція. У нас рядкові значення стабільні, але порядок впливає на `cases()` ітерацію.

3. **Не додавай case `Banned` чи `Hidden` без узгодження** — це міняє бізнес-логіку scopes.

4. **Чому окремий метод `description()`** — Filament v4 має `helperText()` у формах. Зручно показувати пояснення прямо біля Select.

---

## 🧪 Перевірка

```bash
php artisan tinker --execute="
    use App\Enums\VacancyStatus;
    dump(VacancyStatus::Active->label());           // 'Активна'
    dump(VacancyStatus::Active->color());           // 'success'
    dump(VacancyStatus::options());                 // ['draft' => 'Чернетка', ...]
    dump(VacancyStatus::Draft === VacancyStatus::from('draft'));  // true
"
```

---

## ✅ Результат

- Файл `app/Enums/VacancyStatus.php` створено.
- Модель `Vacancy` з модуля 2 коректно кастить `'status' => VacancyStatus::class`.
- Перейти до модуля 4 (Scheduler).
-e 

---


# МОДУЛЬ 4 (розгорнутий). Scheduler — автоматичне маркування `expired`

## 🎯 Мета модуля
Створити надійний фоновий процес, який щогодини сканує активні вакансії та переводить у статус `expired` ті, чий `expires_at` уже минув. Без race conditions, з ідемпотентністю, з логуванням, з можливістю dry-run перед прод-запуском.

**Передумова:** модулі 1–3 виконано.

---

## 🔧 КРОК 4.1. Розвідка інфраструктури

Перевір і відзвітуй мені:

```bash
# Чи є supervisor / cron, який виконує schedule:run?
crontab -l 2>/dev/null | grep schedule
ls -la /etc/cron.d/ 2>/dev/null

# Який драйвер кешу/черг (для withoutOverlapping)?
php artisan tinker --execute="dump(config('cache.default')); dump(config('queue.default'));"

# Які канали логування налаштовано?
php artisan tinker --execute="dump(array_keys(config('logging.channels')));"
```

Я очікую побачити Redis як драйвер кешу — якщо ні, обговоримо альтернативу для locking.

---

## 📂 КРОК 4.2. Створення команди

```bash
php artisan make:command ExpireVacanciesCommand
```

Повний вміст `app/Console/Commands/ExpireVacanciesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireVacanciesCommand extends Command
{
    /**
     * Підтримувані прапорці:
     *   --dry-run        не змінює БД, лише виводить кількість
     *   --batch=500      розмір батча для оновлення (за замовчуванням 500)
     *   --max-runtime=55 максимальний час виконання у секундах (захист від overlap з наступним запуском)
     */
    protected $signature = 'vacancies:expire
                            {--dry-run : Не змінює БД, лише виводить статистику}
                            {--batch=500 : Розмір батча для оновлення}
                            {--max-runtime=55 : Максимальний час виконання у секундах}';

    protected $description = 'Переводить активні вакансії в статус expired, якщо expires_at вже минув';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $isDryRun = (bool) $this->option('dry-run');
        $batchSize = (int) $this->option('batch');
        $maxRuntime = (int) $this->option('max-runtime');

        $logContext = [
            'command'   => 'vacancies:expire',
            'dry_run'   => $isDryRun,
            'batch'     => $batchSize,
            'started_at' => now()->toIso8601String(),
        ];

        $this->info($isDryRun ? 'РЕЖИМ DRY-RUN: змін у БД не буде.' : 'Запуск expire-команди.');
        Log::channel('vacancies')->info('Expire command started', $logContext);

        try {
            $totalExpired = 0;
            $totalScanned = 0;

            // Працюємо батчами, щоб:
            // 1. не блокувати таблицю на довго
            // 2. дозволити graceful exit при наближенні до max-runtime
            Vacancy::query()
                ->where('status', VacancyStatus::Active)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->orderBy('id')  // стабільний порядок для пагінації
                ->chunkById($batchSize, function ($batch) use (
                    &$totalExpired,
                    &$totalScanned,
                    $isDryRun,
                    $startedAt,
                    $maxRuntime
                ) {
                    $totalScanned += $batch->count();

                    if (! $isDryRun) {
                        // Атомарне оновлення батча однією транзакцією
                        DB::transaction(function () use ($batch, &$totalExpired) {
                            $ids = $batch->pluck('id')->all();

                            $affected = Vacancy::query()
                                ->whereIn('id', $ids)
                                ->where('status', VacancyStatus::Active)  // double-check від race з Stripe webhook
                                ->where('expires_at', '<', now())          // double-check від race з extend()
                                ->update(['status' => VacancyStatus::Expired->value]);

                            $totalExpired += $affected;
                        });
                    } else {
                        $totalExpired += $batch->count();
                    }

                    // Перевірка часу — не запускай ще один батч, якщо до ліміту менше 5 секунд
                    $elapsed = microtime(true) - $startedAt;
                    if ($elapsed > ($maxRuntime - 5)) {
                        $this->warn("Досягнуто max-runtime ({$maxRuntime}s), зупиняюсь.");
                        Log::channel('vacancies')->warning('Expire command stopped by max-runtime', [
                            'elapsed_seconds' => round($elapsed, 2),
                            'expired_so_far'  => $totalExpired,
                        ]);
                        return false;  // зупинити chunkById
                    }
                });

            $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

            $message = $isDryRun
                ? "Знайдено {$totalScanned} вакансій для завершення (dry-run, БД не змінено) за {$elapsedMs} мс."
                : "Завершено {$totalExpired} вакансій з {$totalScanned} просканованих за {$elapsedMs} мс.";

            $this->info($message);
            Log::channel('vacancies')->info('Expire command finished', array_merge($logContext, [
                'expired_count' => $totalExpired,
                'scanned_count' => $totalScanned,
                'elapsed_ms'    => $elapsedMs,
            ]));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Помилка: {$e->getMessage()}");
            Log::channel('vacancies')->error('Expire command failed', array_merge($logContext, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));

            // Sentry / Bugsnag підхопить через стандартний report()
            report($e);

            return self::FAILURE;
        }
    }
}
```

---

## 📡 КРОК 4.3. Реєстрація в scheduler

Файл `routes/console.php` (Laravel 11+) або `app/Console/Kernel.php` (старіші):

### Для Laravel 11+ (`routes/console.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('vacancies:expire')
    ->hourly()
    ->withoutOverlapping(10)        // Redis-lock на 10 хвилин — захищає від паралельних запусків
    ->runInBackground()              // не блокує інші scheduled tasks
    ->onOneServer()                  // запуск тільки на одному з підів/серверів (потрібен Redis)
    ->name('vacancies.expire')       // явне ім'я для логів
    ->onFailure(function () {
        Log::channel('vacancies')->error('Scheduled vacancies:expire failed');
    })
    ->onSuccess(function () {
        // Опційно: пушити метрику в Prometheus / Sentry
    });
```

### Для Laravel 10 і старіших (`app/Console/Kernel.php`):

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('vacancies:expire')
        ->hourly()
        ->withoutOverlapping(10)
        ->runInBackground()
        ->onOneServer();
}
```

---

## 📋 КРОК 4.4. Канал логування

Файл `config/logging.php` — додай у масив `'channels'`:

```php
'vacancies' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/vacancies.log'),
    'level'  => env('LOG_LEVEL_VACANCIES', 'info'),
    'days'   => 14,                  // зберігати 2 тижні
    'replace_placeholders' => true,
],
```

Це дає окремий файл для всіх ATS-операцій (expire, extend, notification). Не змішується з основним логом — простіше дебажити.

---

## ⚠️ Критичні нюанси та можливі баги

### 1. Race condition зі Stripe webhook
**Сценарій:** клієнт натиснув «Продовжити», Stripe webhook стартував `extend()`, але ДО завершення транзакції scheduler знайшов цю саму вакансію (бо `expires_at` ще в минулому) і оновив на `expired`.

**Захист (вже в коді):** `double-check WHERE status = 'active' AND expires_at < now()` усередині транзакції оновлення. Якщо webhook встиг оновити `status` на `Active` (нехай уже з новим `expires_at`) — наш UPDATE просто не знайде запис і не зачепить його.

### 2. Race condition між паралельними запусками scheduler
**Сценарій:** з якоїсь причини cron запустив команду двічі (мережевий збій → retry).

**Захист:** `withoutOverlapping(10)` тримає Redis-lock на 10 хвилин. Якщо лок зайнятий — другий запуск негайно завершується.

**УВАГА:** `withoutOverlapping` працює тільки якщо `cache.default` — `redis` / `memcached`. Файловий кеш на multi-server деплої НЕ захистить!

### 3. Чому `chunkById`, а не `chunk`
`chunk()` пагінує через `OFFSET/LIMIT`. Якщо під час обробки запис змінює статус — пагінація зсувається, і ти можеш **пропустити** запис або **обробити двічі**.

`chunkById` пагінує через `WHERE id > last_id` — стабільно навіть при паралельних змінах.

### 4. Чому транзакція тільки на батч, а не на всю команду
Одна велика транзакція на 100 000 записів:
- Блокує таблицю на довгий час → деградація read-запитів від користувачів.
- При rollback'у — катастрофічна втрата прогресу.

Окрема транзакція на 500 записів:
- Швидко commit-иться → блокування короткі.
- При помилці втрачаємо лише останній батч.

### 5. Чому `--max-runtime=55`
Cron запускає кожну годину (60 хв = 3600 с). Якщо команда не встигає за 60 хвилин — `withoutOverlapping(10)` зарубає наступний запуск і дані застаріють ще на годину.

55 секунд (а не 55 хвилин!) — це для випадку, якщо команду викликаємо щохвилини (наприклад, у dev-середовищі з `everyMinute()`). Для прод-`hourly()` цей параметр не критичний, але корисний як safety net.

**Поправка:** для hourly можна використовувати `--max-runtime=3300` (55 хв). Запитай мене перед застосуванням.

### 6. `report($e)` для Sentry
Якщо проєкт інтегрований з Sentry — `report()` автоматично надішле виняток. Якщо ні — нічого не зробить, безпечно. Не оточуй у `if (class_exists(Sentry::class))`.

### 7. Чому НЕ використовувати закриту функцію (closure) у `routes/console.php`
Я навмисно обрав окрему команду, а не closure (як було в оригіналі):

| Closure | Окрема команда |
|---------|----------------|
| Не тестується через PHPUnit | Тестується: `$this->artisan('vacancies:expire')` |
| Немає прапорців (`--dry-run`) | Повноцінний CLI з прапорцями |
| Один логер на все | Кастомне логування і метрики |
| Не можна викликати вручну з іншого коду | `Artisan::call('vacancies:expire')` |
| Складно мокати в тестах | Легко |

---

## 🧪 КРОК 4.5. Перевірка вручну

```bash
# 1. Створити тестові дані
php artisan tinker
```
```php
// Активна, але прострочена
\App\Models\Vacancy::factory()->create([
    'status' => 'active',
    'published_at' => now()->subDays(31),
    'expires_at' => now()->subHours(2),
]);

// Активна, ще не прострочена
\App\Models\Vacancy::factory()->create([
    'status' => 'active',
    'published_at' => now()->subDays(5),
    'expires_at' => now()->addDays(10),
]);

// Уже expired — не повинна бути зачеплена
\App\Models\Vacancy::factory()->create([
    'status' => 'expired',
    'expires_at' => now()->subDays(10),
]);
```

```bash
# 2. Dry-run
php artisan vacancies:expire --dry-run
# Очікуване: "Знайдено 1 вакансію для завершення..."

# 3. Реальний запуск
php artisan vacancies:expire
# Очікуване: "Завершено 1 вакансію з 1 просканованих..."

# 4. Перевір БД
php artisan tinker --execute="dump(\App\Models\Vacancy::expired()->count());"
# Очікуване: 2 (одна нова + одна, що вже була expired)

# 5. Перевір лог
tail -f storage/logs/vacancies-$(date +%Y-%m-%d).log
```

---

## ✅ Очікуваний результат модуля

1. `app/Console/Commands/ExpireVacanciesCommand.php` створено.
2. `routes/console.php` (або `Kernel.php`) оновлено.
3. `config/logging.php` має канал `vacancies`.
4. Ручне тестування пройдено (5 кроків вище).
5. Звіт мені:
   ```
   Команду vacancies:expire створено.
   Зареєстровано в scheduler-і: hourly + withoutOverlapping(10).
   Лог-канал vacancies налаштовано.
   Ручний тест: 1 вакансію переведено в expired.

   Перейти до модуля 5 (Filament)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не запускай команду на проді без попереднього `--dry-run`.
- ❌ Не виконуй `withoutOverlapping()` без Redis — на файловому кеші зламається на multi-server.
- ❌ Не використовуй `Vacancy::all()->each(...)` — це завантажить ВСІ записи в пам'ять. Тільки `chunkById`.
- ❌ Не додавай у scheduler нотифікації Telegram — це модуль 8.
-e 

---


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
- Перейти до модуля 6 (Stripe webhook).
-e 

---


# МОДУЛЬ 6 (розгорнутий). Stripe Webhook — оплата = продовження вакансії

## 🎯 Мета модуля
Інтеграція платежів Stripe з життєвим циклом вакансії. Коли клієнт оплачує продовження публікації — webhook перевіряє підпис, ідемпотентно обробляє подію, продовжує термін на куплену кількість днів і кидає Laravel-подію `VacancyExtended`, яку слухає модуль 8 (Nutgram).

**Передумова:** модулі 1–5 виконано. У `composer.json` повинен бути пакет `stripe/stripe-php` (`^15.0` або новіший).

> **Це найкритичніший модуль за наслідками.** Помилка тут = втрачені гроші, дублікати оплат, або вакансії, не продовжені після списання коштів. **Усі CRITICAL-нюанси нижче — обов'язкові.**

---

## 🔍 КРОК 6.1. Розвідка інтеграції

Перш ніж писати код — виведи мені:

```bash
# 1. Версія пакету
composer show stripe/stripe-php | grep versions

# 2. Чи є вже контролер вебхуків?
find app -type f -name "*StripeWebhook*"
find app -type f -name "*Webhook*Controller*"

# 3. Які роути зареєстровані?
php artisan route:list | grep -i stripe

# 4. Які env-змінні налаштовані (НЕ показуй значення, тільки список ключів!)
grep -E '^STRIPE_' .env.example
grep -E '^STRIPE_' .env | awk -F= '{print $1}'

# 5. Чи існує таблиця payments?
php artisan tinker --execute="dump(Schema::hasTable('payments'));"

# 6. Чи існує таблиця stripe_processed_events (idempotency)?
php artisan tinker --execute="dump(Schema::hasTable('stripe_processed_events'));"
```

**Не пиши код, поки я не побачу цей звіт і не дам OK на стратегію.**

---

## 🗺️ КРОК 6.2. Стратегія залежно від наявного коду

| Ситуація | Дія |
|----------|-----|
| Контролера вебхуків НЕМАЄ | Створюй `StripeWebhookController` з нуля (нижче) |
| Контролер є, але без `checkout.session.completed` | Додай **тільки** новий метод-обробник, не зачіпай інших |
| Контролер є, з `checkout.session.completed` уже обробляється для іншого продукту | **СТОП.** Обговори зі мною — як розрізняти типи checkout (по `metadata.type`?) |
| Таблиці `payments` немає | Логи в окремий канал `payments` + TODO. Не створюй таблицю в цьому модулі |
| Таблиці `stripe_processed_events` немає | Створюй (нижче) — без неї немає захисту від дублікатів |

---

## 📂 КРОК 6.3. Міграція для idempotency

```bash
php artisan make:migration create_stripe_processed_events_table
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_processed_events', function (Blueprint $table) {
            $table->string('event_id')->primary();         // evt_1NXxxxx — Stripe event id
            $table->string('event_type', 64);              // checkout.session.completed
            $table->timestamp('processed_at')->useCurrent();
            $table->index('processed_at');                 // для періодичного очищення старих
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_processed_events');
    }
};
```

**Альтернатива через Redis** (якщо не хочеш окрему таблицю): використовуй `Cache::add("stripe:event:{$eventId}", true, now()->addDays(7))`. Повертає `false`, якщо ключ уже існує — це і є idempotency.

**Рекомендую таблицю**, бо:
- Перегляд історії через адмінку
- Не зникає при flush Redis
- Можна додати додаткові поля (`vacancy_id`, `amount`) для аудиту

---

## 📂 КРОК 6.4. Подія `VacancyExtended`

```bash
php artisan make:event VacancyExtended
```

`app/Events/VacancyExtended.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Vacancy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VacancyExtended
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vacancy $vacancy,
        public readonly int $days,
        public readonly int $amountCents,         // у копійках/центах для логу
        public readonly string $currency,         // ISO 4217: UAH / USD
        public readonly string $stripeEventId,    // для трасування
    ) {}
}
```

---

## 📂 КРОК 6.5. Контролер вебхуків

Якщо контролера ще немає — створи `app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use App\Models\Vacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Endpoint, на який Stripe постить події.
     * Маршрут: POST /webhooks/stripe (без CSRF — додай у VerifyCsrfToken::$except)
     */
    public function handle(Request $request): JsonResponse
    {
        // CRITICAL #1: верифікація підпису
        try {
            $event = Webhook::constructEvent(
                payload: $request->getContent(),
                sigHeader: $request->header('Stripe-Signature', ''),
                secret: config('services.stripe.webhook_secret'),
                tolerance: 300,  // 5 хвилин — стандарт Stripe
            );
        } catch (SignatureVerificationException $e) {
            Log::channel('payments')->warning('Stripe webhook: invalid signature', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::channel('payments')->warning('Stripe webhook: invalid payload', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // CRITICAL #2: idempotency — не обробляємо ту саму подію двічі
        $alreadyProcessed = DB::table('stripe_processed_events')
            ->where('event_id', $event->id)
            ->exists();

        if ($alreadyProcessed) {
            Log::channel('payments')->info('Stripe webhook: duplicate event ignored', [
                'event_id'   => $event->id,
                'event_type' => $event->type,
            ]);
            return response()->json(['status' => 'duplicate'], 200);
        }

        // Роутинг подій
        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event),
                // 'invoice.payment_failed'   => $this->handlePaymentFailed($event),  // майбутнє
                default => Log::channel('payments')->info('Stripe webhook: unhandled event type', [
                    'event_id'   => $event->id,
                    'event_type' => $event->type,
                ]),
            };

            // CRITICAL #3: позначаємо як оброблене ТІЛЬКИ після успіху бізнес-логіки
            DB::table('stripe_processed_events')->insert([
                'event_id'     => $event->id,
                'event_type'   => $event->type,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // CRITICAL #4: НЕ повертаємо 500 — Stripe буде retry-їти й ми отримаємо ту саму помилку.
            // Логуємо і повертаємо 200, щоб Stripe не дублював.
            // Прод-моніторинг (Sentry) поінформує нас.
            Log::channel('payments')->error('Stripe webhook: handler failed', [
                'event_id'   => $event->id,
                'event_type' => $event->type,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            report($e);

            // ВИНЯТОК: для критичних помилок верифікації даних (наприклад, vacancy_id не знайдено)
            // — теж 200, бо retry не виправить ситуацію.
            return response()->json(['status' => 'error_logged'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Обробка події `checkout.session.completed` для продовження вакансії.
     */
    private function handleCheckoutCompleted(Event $event): void
    {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        $logContext = [
            'event_id'   => $event->id,
            'session_id' => $session->id,
            'metadata'   => $session->metadata?->toArray() ?? [],
        ];

        // CRITICAL #5: розрізняємо тип checkout по metadata.type
        $type = $session->metadata->type ?? null;
        if ($type !== 'vacancy_extension') {
            Log::channel('payments')->info('Stripe webhook: not a vacancy extension, skipping', $logContext);
            return;
        }

        // Валідація payment_status
        if ($session->payment_status !== 'paid') {
            Log::channel('payments')->warning('Stripe webhook: session not paid yet', array_merge(
                $logContext,
                ['payment_status' => $session->payment_status],
            ));
            return;
        }

        // Витягуємо метадані
        $vacancyId = (int) ($session->metadata->vacancy_id ?? 0);
        $days = (int) ($session->metadata->days ?? 0);

        if ($vacancyId <= 0 || ! in_array($days, [15, 30, 90], true)) {
            // Логуємо як критичну помилку — це означає, що checkout створено з некоректним metadata
            Log::channel('payments')->error('Stripe webhook: invalid metadata', array_merge(
                $logContext,
                ['vacancy_id' => $vacancyId, 'days' => $days],
            ));
            return;
        }

        // CRITICAL #6: транзакційне продовження
        DB::transaction(function () use ($vacancyId, $days, $session, $event, $logContext) {
            // Lock для запобігання race condition зі scheduler-ом (модуль 4)
            $vacancy = Vacancy::query()->lockForUpdate()->find($vacancyId);

            if (! $vacancy) {
                Log::channel('payments')->error('Stripe webhook: vacancy not found', array_merge(
                    $logContext,
                    ['vacancy_id' => $vacancyId],
                ));
                return;
            }

            // НЕ можна продовжити архівовану — це бізнес-обмеження
            if ($vacancy->status === VacancyStatus::Archived) {
                Log::channel('payments')->warning('Stripe webhook: cannot extend archived vacancy', array_merge(
                    $logContext,
                    ['vacancy_id' => $vacancyId],
                ));
                // ⚠️ Гроші вже списані. Тут треба запустити refund через Stripe API
                // або хоча б створити запис у tasks для ручної обробки.
                $this->createRefundTask($session, $vacancy, 'vacancy_archived');
                return;
            }

            // Власне продовження
            $vacancy->extend($days);

            // CRITICAL #7: подія для слухачів (Nutgram, аналітика, листи)
            VacancyExtended::dispatch(
                vacancy: $vacancy,
                days: $days,
                amountCents: (int) $session->amount_total,
                currency: strtoupper((string) $session->currency),
                stripeEventId: $event->id,
            );

            Log::channel('payments')->info('Stripe webhook: vacancy extended', array_merge(
                $logContext,
                [
                    'vacancy_id'   => $vacancy->id,
                    'days'         => $days,
                    'new_expires_at' => $vacancy->expires_at?->toIso8601String(),
                ],
            ));
        });
    }

    /**
     * Заглушка для майбутньої таблиці refund_tasks.
     * Поки що — лог + Sentry alert.
     */
    private function createRefundTask(\Stripe\Checkout\Session $session, Vacancy $vacancy, string $reason): void
    {
        Log::channel('payments')->critical('Stripe webhook: REFUND REQUIRED', [
            'session_id'    => $session->id,
            'payment_intent' => $session->payment_intent,
            'vacancy_id'    => $vacancy->id,
            'amount'        => $session->amount_total,
            'currency'      => $session->currency,
            'reason'        => $reason,
        ]);

        // TODO: коли з'явиться таблиця refund_tasks — створювати запис тут.
        // Поки що Sentry-алерт через level=critical і ручне реагування.
    }
}
```

---

## 🔧 КРОК 6.6. Реєстрація маршруту

Файл `routes/web.php` (або `routes/api.php` — обрати один!):

```php
use App\Http\Controllers\StripeWebhookController;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
```

**КРИТИЧНО:** додай URI до `VerifyCsrfToken::$except` (або `bootstrap/app.php` для Laravel 11+):

```php
// app/Http/Middleware/VerifyCsrfToken.php — Laravel 10
protected $except = [
    'webhooks/stripe',
];
```

```php
// bootstrap/app.php — Laravel 11+
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhooks/stripe',
    ]);
})
```

---

## 🔐 КРОК 6.7. Конфігурація `services.php` та `.env`

Файл `config/services.php`:

```php
'stripe' => [
    'key'            => env('STRIPE_KEY'),
    'secret'         => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

Файл `.env.example`:

```ini
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

**УВАГА:** `STRIPE_WEBHOOK_SECRET` — це окремий секрет від `STRIPE_SECRET`. Береться з Dashboard → Developers → Webhooks → твій endpoint → Signing secret.

---

## 🛒 КРОК 6.8. Створення Checkout Session (контекст для розуміння)

Ось як ВИКОРИСТОВУЄТЬСЯ цей webhook (НЕ пиши цей код у модулі 6 — це модуль для оплати, який буде окремо):

```php
// Десь у VacancyController@createCheckout — поки референс
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency'     => 'uah',
            'unit_amount'  => 20000,  // 200 грн у копійках
            'product_data' => ['name' => "Продовження вакансії на {$days} днів"],
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => route('vacancies.show', $vacancy),
    'cancel_url'  => route('vacancies.show', $vacancy),
    'metadata' => [
        'type'       => 'vacancy_extension',  // КРИТИЧНО — інакше webhook пропустить
        'vacancy_id' => (string) $vacancy->id,
        'days'       => (string) $days,        // лише '15', '30', '90'
    ],
]);
```

Метадані Stripe — завжди рядки! `(string)` обов'язково.

---

## 🧪 КРОК 6.9. Локальне тестування зі Stripe CLI

```bash
# 1. Установи Stripe CLI (один раз)
# https://stripe.com/docs/stripe-cli

# 2. Залогінься
stripe login

# 3. Перенаправ події на локальний сервер
stripe listen --forward-to localhost:8000/webhooks/stripe
# Виведе webhook_signing_secret — скопіюй у .env як STRIPE_WEBHOOK_SECRET

# 4. У ОКРЕМОМУ терміналі — тригер тестової події
stripe trigger checkout.session.completed \
  --add checkout_session:metadata.type=vacancy_extension \
  --add checkout_session:metadata.vacancy_id=1 \
  --add checkout_session:metadata.days=30
```

**Очікуваний результат:**
- Лог `storage/logs/payments-YYYY-MM-DD.log` містить запис `Stripe webhook: vacancy extended`.
- У БД: вакансія #1 має `expires_at` на 30 днів пізніше.
- У БД: таблиця `stripe_processed_events` має новий запис.

---

## ⚠️ Критичні нюанси (підсумок)

### CRITICAL #1: Верифікація підпису ДО будь-якого парсингу
Якщо обходити цей крок — будь-хто може POST-ити фейкові події і безкоштовно продовжувати вакансії. **Ніколи** не починай обробку до `Webhook::constructEvent()`.

### CRITICAL #2: Idempotency — таблиця або Redis
Stripe **гарантує at-least-once delivery**. Це означає, що ту саму подію ти отримаєш 2-3 рази (мережеві проблеми, retry policy). Без idempotency-перевірки клієнт отримає 60 днів замість 30.

### CRITICAL #3: Запис у `stripe_processed_events` ПІСЛЯ бізнес-логіки
Якщо записати ДО — і потім бізнес-логіка кидає виняток — Stripe ретраїть, але наша система каже «вже оброблено», і клієнт не отримає продовження. **Завжди** записуй після успіху.

### CRITICAL #4: 200 OK навіть на бізнес-помилки
Stripe реагує на 5xx як на «треба ретраїти». Якщо помилка фіксована (наприклад, vacancy_id не існує) — повторні спроби нічого не дадуть, лише засмітять логи. Логуй як `error`, надсилай у Sentry, повертай 200.

**Виняток:** на 4xx (invalid signature) — повертай 400. Stripe позначить webhook як зламаний, ти отримаєш email.

### CRITICAL #5: Розрізнення типів checkout
Якщо в проєкті будуть інші продукти через Stripe (преміум-акаунти, реклама) — без `metadata.type` ти не зможеш розрізнити їх у webhook. Це треба закладати **зараз**, поки checkout-ів мало.

### CRITICAL #6: `lockForUpdate()` у транзакції
Без локу: scheduler може між `find()` і `extend()` встигнути перевести в `expired`, а наш `extend()` цього не побачить (через те, що в моделі логіка враховує поточний статус). З `lockForUpdate()` — інші транзакції чекають на нашу.

### CRITICAL #7: Подія `VacancyExtended`, а не прямий виклик нотифікацій
Спокусливо одразу в webhook викликати `Notification::send($employer, new VacancyExtendedNotification(...))`. **Не треба.** Чому:

- Webhook повинен бути швидким (<5 секунд відповіді), інакше Stripe ретраїть.
- Telegram API може лежати → нотифікація фейлиться → весь webhook фейлиться → Stripe ретраїть → дубль.
- Подія ставиться в чергу через listener → webhook повертає 200 одразу → нотифікація летить асинхронно.

Listener для події (модуль 8) обов'язково реалізує `ShouldQueue`.

### CRITICAL #8: Refund для archived
Якщо клієнт оплатив, але вакансія archived (хтось встиг архівувати між кліком і оплатою) — гроші вже списані. Це треба refund-ити. У цьому модулі — лише log + alert; повний refund-механізм — окрема історія.

### CRITICAL #9: Що бачить користувач при failed webhook
Stripe redirect-ить на `success_url` миттєво після оплати. Webhook прилітає **окремо**, з затримкою 1-30 секунд. Це означає:

- Не показуй на success-сторінці «Вакансію продовжено!» — бо ще не точно. Покажи «Оплату прийнято, оновлюємо публікацію...» з polling-перевіркою через Livewire.
- Це окрема історія для модуля «Stripe Checkout flow», поза цим модулем.

---

## ✅ Очікуваний результат модуля

1. Міграція `create_stripe_processed_events_table` створена та виконана.
2. Подія `App\Events\VacancyExtended` створена.
3. Контролер `StripeWebhookController` створено / оновлено.
4. Маршрут зареєстровано, CSRF виключено.
5. `config/services.php` має `stripe.webhook_secret`.
6. `.env.example` оновлено.
7. Канал логування `payments` додано в `config/logging.php`:
```php
'payments' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/payments.log'),
    'level'  => 'debug',
    'days'   => 90,  // 3 місяці — для аудиту платежів
],
```
8. Локальне тестування зі Stripe CLI пройдено успішно.
9. Звіт мені:
   ```
   Webhook налаштовано. Verified signature, idempotency через таблицю,
   подія VacancyExtended відправляється.

   Локальний тест зі Stripe CLI:
   - Тригер checkout.session.completed → вакансія #1 продовжена на 30 днів
   - Дублікат-тригер → ігнорується (200 'duplicate')
   - Невалідний підпис → 400

   Перейти до модуля 7 (Livewire countdown)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не використовуй `Cashier` (laravel/cashier-stripe) для одноразових платежів — він заточений під підписки. Прямий API простіший.
- ❌ Не парси webhook payload вручну через `json_decode($request->getContent())` — використовуй `Webhook::constructEvent()`, бо він робить і парсинг, і верифікацію.
- ❌ Не зберігай `STRIPE_WEBHOOK_SECRET` у БД чи коді — тільки в `.env`.
- ❌ Не додавай `auth` middleware на роут вебхука — Stripe не має сесії, він підписує запит секретом.
- ❌ Не реалізуй refund-логіку в цьому модулі — окремий епік.
- ❌ Не реалізуй Telegram-нотифікацію в цьому модулі — модуль 8 через `VacancyExtended` listener.
-e 

---


# МОДУЛЬ 7 (розгорнутий). Livewire/Volt — лічильник «Залишилось N днів» у реальному часі

## 🎯 Мета модуля
Створити reusable Livewire-компонент, який показує роботодавцю стан його вакансії: статус, залишок часу до завершення, і CTA-кнопку «Продовжити публікацію». Має оновлюватися сам без перезавантаження сторінки, з правильною українською плюралізацією, з адаптивним дизайном на Tailwind.

**Передумова:** модулі 1–4 виконано (модель `Vacancy` має `countdown_label`, `is_active`, scopes).

---

## 🤔 КРОК 7.1. Питання до мене перед стартом

Виведи мені поточний стан і запитай:

```bash
# 1. Який Livewire?
composer show livewire/livewire | grep versions
composer show livewire/volt | grep versions 2>/dev/null

# 2. Чи в проєкті використовується Volt (single-file components) чи класичний Livewire?
ls resources/views/livewire/ 2>/dev/null | head -5
ls app/Livewire/ 2>/dev/null | head -5

# 3. Сторінка перегляду вакансії роботодавцем — це окремий Livewire-компонент чи Blade-вʼюшка?
find resources/views -name "*vacanc*" -type f
find app/Livewire -name "*Vacanc*" -type f 2>/dev/null
```

**Запитай мене:**
> Я бачу `<що знайдено>`. Куди вбудувати компонент countdown — у вже наявну сторінку перегляду чи це новий екран? Який стиль реалізації — Volt single-file чи класичний клас + Blade?

---

## 🎨 КРОК 7.2. Дизайн-специфікація

```
┌──────────────────────────────────────────────────┐
│  🟢 Активна публікація                           │  ← статус-бейдж (color із enum)
│                                                  │
│  Залишилось 3 дні 14 годин                       │  ← основний лічильник
│  до 25 листопада 2025, 18:30                     │  ← повна дата (підказка)
│                                                  │
│  ████████░░░░░░░░░░░░░░░░░░░░░  10%              │  ← прогрес-бар (за бажанням)
│                                                  │
│  [ Продовжити публікацію ]                       │  ← primary CTA
│  [ Архівувати ]                                  │  ← secondary
└──────────────────────────────────────────────────┘
```

**Стани компонента:**

| `vacancy.status` | Бейдж | Лічильник | Кнопки |
|------------------|-------|-----------|--------|
| `Draft` | сірий «Чернетка» | «Не опубліковано» | `[Опублікувати]` |
| `Active`, > 24h | зелений «Активна» | «Залишилось N днів» | `[Продовжити] [Архівувати]` |
| `Active`, < 24h | жовтий «Завершується» | «Залишилось 14 годин» | `[Продовжити (зробити primary, акцент)] [Архівувати]` |
| `Active`, < 1h | червоний «Завершується» | «Залишилось 32 хвилини» | `[Продовжити]` (з пульсацією) |
| `Expired` | червоний «Завершено» | «Публікацію завершено N днів тому» | `[Поновити публікацію]` |
| `Archived` | сірий «В архіві» | «В архіві» | `[Відновити]` (якщо дозволено) |

---

## 📂 КРОК 7.3. Реалізація — варіант Volt (single-file)

Якщо проєкт на Volt, файл `resources/views/livewire/vacancy-countdown.blade.php`:

```php
<?php

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Livewire\Volt\Component;

new class extends Component {
    public Vacancy $vacancy;

    /**
     * Період опитування у секундах.
     * 60 — оптимально: достатньо часто, щоб лічильник не «застрягав»,
     * але не настільки часто, щоб створити навантаження на сервер.
     */
    public int $pollInterval = 60;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy = $vacancy;
    }

    /**
     * Поновлення даних — викликається wire:poll.
     * Можна навіть не оголошувати: wire:poll сам ререндерить компонент.
     * Але явний refresh() корисний для тестів і для майбутніх дій.
     */
    public function refresh(): void
    {
        $this->vacancy->refresh();
    }

    /**
     * Чи треба показувати «терміновий» режим (червоний акцент, пульсація)?
     */
    public function getIsCriticalProperty(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 24;
    }

    /**
     * Чи треба показувати «попередній» режим (жовтий)?
     */
    public function getIsWarningProperty(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 72
            && ! $this->is_critical;
    }

    /**
     * Прогрес-бар: відсоток ВИКОРИСТАНОГО часу публікації.
     * 0% — щойно опублікували, 100% — час вийшов.
     */
    public function getProgressPercentProperty(): int
    {
        if (! $this->vacancy->is_active || ! $this->vacancy->expires_at || ! $this->vacancy->published_at) {
            return 0;
        }

        $totalSeconds = $this->vacancy->published_at->diffInSeconds($this->vacancy->expires_at, absolute: true);
        $elapsedSeconds = $this->vacancy->published_at->diffInSeconds(now(), absolute: true);

        if ($totalSeconds === 0) {
            return 100;
        }

        return min(100, max(0, (int) round($elapsedSeconds / $totalSeconds * 100)));
    }

    /**
     * Опис для expired: «Завершилась 5 днів тому».
     */
    public function getExpiredAgoLabelProperty(): ?string
    {
        if ($this->vacancy->status !== VacancyStatus::Expired || ! $this->vacancy->expires_at) {
            return null;
        }

        // Carbon має вбудовану українську локалізацію
        return 'Завершено ' . $this->vacancy->expires_at->locale('uk')->diffForHumans();
    }
}; ?>

<div
    wire:poll.{{ $pollInterval }}s="refresh"
    class="rounded-lg border bg-white p-6 shadow-sm @if($this->is_critical) border-red-300 @elseif($this->is_warning) border-yellow-300 @else border-gray-200 @endif"
    aria-live="polite"
>
    {{-- Бейдж статусу --}}
    <div class="flex items-center gap-2 mb-4">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $vacancy->status->badgeClass() }}">
            <span class="w-1.5 h-1.5 rounded-full
                @if($vacancy->status === VacancyStatus::Active) bg-green-500 @if($this->is_critical)animate-pulse @endif
                @elseif($vacancy->status === VacancyStatus::Expired) bg-yellow-500
                @elseif($vacancy->status === VacancyStatus::Archived) bg-red-500
                @else bg-gray-500
                @endif
            "></span>
            {{ $vacancy->status->label() }}
        </span>

        @if($this->is_critical)
            <span class="text-xs text-red-600 font-medium">⚠ Терміново</span>
        @endif
    </div>

    {{-- Основний лічильник --}}
    <div class="space-y-1">
        <p class="text-2xl font-semibold @if($this->is_critical) text-red-700 @elseif($this->is_warning) text-yellow-700 @else text-gray-900 @endif">
            {{ $vacancy->countdown_label }}
        </p>

        @if($vacancy->is_active && $vacancy->expires_at)
            <p class="text-sm text-gray-500">
                до {{ $vacancy->expires_at->locale('uk')->isoFormat('D MMMM YYYY, HH:mm') }}
            </p>
        @elseif($expiredAgoLabel = $this->expired_ago_label)
            <p class="text-sm text-gray-500">{{ $expiredAgoLabel }}</p>
        @endif
    </div>

    {{-- Прогрес-бар (тільки для активних) --}}
    @if($vacancy->is_active)
        <div class="mt-4">
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full transition-all duration-1000 ease-out
                        @if($this->is_critical) bg-red-500 @elseif($this->is_warning) bg-yellow-500 @else bg-green-500 @endif"
                    style="width: {{ $this->progress_percent }}%"
                    role="progressbar"
                    aria-valuenow="{{ $this->progress_percent }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                ></div>
            </div>
            <p class="mt-1 text-xs text-gray-400 text-right">{{ $this->progress_percent }}% часу публікації минуло</p>
        </div>
    @endif

    {{-- Кнопки дій --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @if($vacancy->status === VacancyStatus::Active || $vacancy->status === VacancyStatus::Expired)
            <a
                href="{{ route('vacancies.extend', $vacancy) }}"
                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium
                    @if($this->is_critical) bg-red-600 hover:bg-red-700 text-white
                    @else bg-blue-600 hover:bg-blue-700 text-white
                    @endif"
            >
                @if($vacancy->status === VacancyStatus::Expired)
                    Поновити публікацію
                @else
                    Продовжити публікацію
                @endif
            </a>

            @if($vacancy->status === VacancyStatus::Active)
                <button
                    wire:click="$dispatch('open-archive-modal', { id: {{ $vacancy->id }} })"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium border border-gray-300 hover:bg-gray-50 text-gray-700"
                >
                    Архівувати
                </button>
            @endif
        @elseif($vacancy->status === VacancyStatus::Draft)
            <a
                href="{{ route('vacancies.publish', $vacancy) }}"
                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white"
            >
                Опублікувати
            </a>
        @endif
    </div>
</div>
```

---

## 📂 КРОК 7.4. Альтернатива — класичний Livewire

Якщо проєкт НЕ на Volt:

```bash
php artisan make:livewire VacancyCountdown
```

`app/Livewire/VacancyCountdown.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Livewire\Attributes\Computed;
use Livewire\Component;

class VacancyCountdown extends Component
{
    public Vacancy $vacancy;
    public int $pollInterval = 60;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy = $vacancy;
    }

    public function refresh(): void
    {
        $this->vacancy->refresh();
    }

    #[Computed]
    public function isCritical(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 24;
    }

    #[Computed]
    public function isWarning(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 72
            && ! $this->isCritical;
    }

    #[Computed]
    public function progressPercent(): int
    {
        if (! $this->vacancy->is_active || ! $this->vacancy->expires_at || ! $this->vacancy->published_at) {
            return 0;
        }

        $total = $this->vacancy->published_at->diffInSeconds($this->vacancy->expires_at, absolute: true);
        $elapsed = $this->vacancy->published_at->diffInSeconds(now(), absolute: true);

        return $total === 0 ? 100 : min(100, max(0, (int) round($elapsed / $total * 100)));
    }

    public function render()
    {
        return view('livewire.vacancy-countdown');
    }
}
```

Шаблон `resources/views/livewire/vacancy-countdown.blade.php` — той самий, що в Volt-варіанті, але БЕЗ `<?php new class extends Component {...} ?>` блоку.

---

## 🔌 КРОК 7.5. Вбудовування в сторінку

У сторінці перегляду вакансії роботодавцем (наприклад, `resources/views/employer/vacancies/show.blade.php`):

```blade
<x-employer.layout>
    {{-- Інші блоки сторінки --}}

    {{-- Лічильник у sidebar --}}
    <aside class="sticky top-4">
        <livewire:vacancy-countdown :vacancy="$vacancy" :wire:key="'countdown-'.$vacancy->id" />
    </aside>
</x-employer.layout>
```

**Важливо:** `wire:key` обов'язковий, якщо на сторінці може бути більше одного компонента (наприклад, у списку вакансій).

---

## 🎭 КРОК 7.6. Локалізація Carbon

Файл `config/app.php`:

```php
'locale' => 'uk',
'timezone' => 'Europe/Kyiv',
```

Файл `bootstrap/app.php` (Laravel 11+) або `AppServiceProvider::boot()`:

```php
\Carbon\Carbon::setLocale('uk');
```

Без цього `diffForHumans()` поверне англійською («2 days ago» замість «2 дні тому»).

**Перевірка:**
```php
php artisan tinker --execute="echo now()->subDays(2)->diffForHumans();"
// Очікуване: "2 дні тому"
```

---

## ⚠️ Критичні нюанси

### 1. Чому `wire:poll.60s`, а не `setInterval` JS
- `wire:poll` — тривіально тестується через `Livewire::test()->call('refresh')`.
- Стейт переходить через сервер, тобто завжди консистентний з БД (після scheduler-а).
- Можна вимкнути для прихованої вкладки автоматично (Livewire 3+ це робить).

### 2. Чому 60 секунд, а не кожну секунду
- При секундному поллінгу 1000 одночасних користувачів = 1000 запитів/сек на бекенд лише для лічильника. Reading-heavy.
- Користувач на пів-хвилини точність не помітить — для дня/години 60s достатньо.
- Якщо потрібен SECOND-level countdown — зроби це **на JS**, без Livewire (Alpine.js + computed з `expires_at`), без серверного rountrip.

### 3. AlpineJS-альтернатива для секундного відліку (опційно)
Якщо все ж потрібно «3 дні 14 годин 23 хвилини 47 секунд» — додай поверх Livewire-блоку:

```blade
<div
    x-data="{
        expiresAt: new Date('{{ $vacancy->expires_at?->toIso8601String() }}').getTime(),
        now: Date.now(),
        get secondsLeft() { return Math.max(0, Math.floor((this.expiresAt - this.now) / 1000)); },
        get formatted() {
            const s = this.secondsLeft;
            const d = Math.floor(s / 86400);
            const h = Math.floor((s % 86400) / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return `${d}д ${h}г ${m}х ${sec}с`;
        }
    }"
    x-init="setInterval(() => now = Date.now(), 1000)"
    x-text="formatted"
    class="font-mono text-sm text-gray-500"
></div>
```

**АЛЕ:** клієнтський час може бути неправильним (зсунутий годинник). Якщо це важливо — використовуй `wire:poll` як sync-point.

### 4. Дублікат логіки плюралізації — вже в моделі
Не пиши плюралізацію в Blade. У моделі є `countdown_label` — там уже все правильно. Якщо в Blade хочеться кастомний формат — додай ще один accessor у моделі (наприклад, `short_countdown_label`), а не дублюй логіку.

### 5. `aria-live="polite"` для скрін-рідерів
Незрячі користувачі повинні почути зміну стану, але не бути перебитими (як з `assertive`). `polite` каже «оголоси при найближчій паузі».

### 6. Чому поллінг `wire:poll`, а НЕ Laravel Echo / Reverb
- Echo/Reverb потребує WebSocket-сервера (Reverb або Pusher). Це інфраструктурна залежність.
- Echo має сенс, коли ОНОВЛЕННЯ — НЕЧАСТЕ, але ВАЖЛИВЕ (новий applicant). Лічильник — інше: він поступово «зменшується», і тут pulling простіший.
- Якщо в проєкті Reverb уже налаштовано — обговори зі мною, чи варто переходити на broadcasting.

### 7. SSR / first paint
При першому завантаженні сторінки `countdown_label` обчислюється на сервері (через accessor моделі). Тобто користувач БАЧИТЬ значення одразу, ще до того, як Livewire/Alpine ініціалізується. Це CLS-friendly.

### 8. Не використовуй `wire:poll.keep-alive`
`keep-alive` тримає сесію живою, навіть коли вкладка прихована. Це антифіча для нашого кейсу — користувач переключився на іншу вкладку, нам не потрібно дзюрити запити для нього.

---

## 🧪 КРОК 7.7. Перевірка вручну

```bash
# 1. Створити тестову вакансію
php artisan tinker
```
```php
$v = Vacancy::factory()->create([
    'status' => 'active',
    'published_at' => now()->subDays(28),
    'expires_at' => now()->addDays(2),
]);
echo $v->id;
```

```bash
# 2. Відкрий сторінку перегляду
# http://localhost:8000/employer/vacancies/{id}

# 3. Перевір у DevTools:
#    - Network tab: запит wire:poll кожні 60 секунд
#    - HTML містить wire:poll.60s атрибут
#    - aria-live="polite"

# 4. Скоротити термін через tinker, перезавантажити вручну:
$v->update(['expires_at' => now()->addHours(2)]);
# Очікуване: бейдж стає жовтим, лейбл "Залишилось 2 години"

# 5. Зробити expired:
$v->update(['expires_at' => now()->subMinute(), 'status' => 'expired']);
# Очікуване: червоний бейдж, "Публікацію завершено хвилину тому", кнопка "Поновити"
```

---

## ✅ Очікуваний результат модуля

1. Компонент `VacancyCountdown` створено (Volt або класичний — за вибором).
2. Шаблон з усіма станами (Draft, Active, Expired, Archived).
3. Прогрес-бар з трьома кольорами (зелений / жовтий / червоний).
4. Українська локалізація Carbon працює.
5. Інтегровано в сторінку перегляду вакансії.
6. Ручний тест пройдено.
7. Звіт мені:
   ```
   Livewire-компонент VacancyCountdown готовий.
   wire:poll.60s, прогрес-бар, три рівні попередження.
   Інтегровано в employer/vacancies/show.

   Перейти до модуля 8 (Nutgram)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не додавай інлайн-стилі — тільки Tailwind utility-класи.
- ❌ Не пиши `setInterval` для серверних оновлень — використовуй `wire:poll`.
- ❌ Не використовуй `wire:poll.1s` — це DDoS на власний сервер.
- ❌ Не дублюй плюралізацію в Blade — використовуй accessors моделі.
- ❌ Не додавай у компонент логіку оплати чи архівації — лише посилання/dispatch на інші компоненти.
- ❌ Не зберігай `expires_at` у JS-таймстемпі без врахування TZ — використовуй `toIso8601String()`.
-e 

---


# МОДУЛЬ 8 (розгорнутий). Nutgram — Telegram-сповіщення «вакансія скоро завершиться»

## 🎯 Мета модуля
За 24 години до завершення публікації Telegram-бот надсилає роботодавцю повідомлення з inline-кнопками: продовжити (на 15/30/90 днів), архівувати, заглушити нагадування. Натискання кнопок — без перекидання в браузер; усе обробляється в боті, з deep-link на оплату Stripe лише для extension.

**Передумова:** модулі 1–7 виконано. У `composer.json` має бути `nutgram/nutgram` (^4 або новіший).

---

## 🔍 КРОК 8.1. Розвідка інтеграції

```bash
# 1. Версія Nutgram
composer show nutgram/nutgram | grep versions

# 2. Service provider Nutgram налаштовано?
find app/Providers -name "*Nutgram*"
ls config/nutgram.php 2>/dev/null

# 3. Чи має User / Employer поле для зв'язування з Telegram?
php artisan tinker --execute="
    dump(in_array('telegram_chat_id', Schema::getColumnListing('users')));
    dump(in_array('telegram_chat_id', Schema::getColumnListing('employers')) ?: 'no employers table');
"

# 4. Чи є вже команди / handlers Nutgram?
ls app/Telegram/ 2>/dev/null
ls routes/ | grep -i tele

# 5. Чи бот налаштований у вебхук-режимі чи polling?
grep -E '^TELEGRAM_' .env.example
```

**Запитай мене ДО написання коду:**
> 1. Поле `telegram_chat_id` — у `users` чи в `employers`? Чи воно `nullable`?
> 2. Як саме зв'язується акаунт — через `/start` команду в боті з deep-link токеном, чи через явний QR/ввід в особистому кабінеті?
> 3. Який поточний роутинг в Nutgram (мій код далі — каркас, я підлаштуюсь)?

---

## 📂 КРОК 8.2. Міграція (якщо `telegram_chat_id` немає)

```bash
php artisan make:migration add_telegram_chat_id_to_users_table
```

```php
Schema::table('users', function (Blueprint $table) {
    $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('email');
    $table->boolean('telegram_notifications_enabled')->default(true)->after('telegram_chat_id');
});
```

`bigInteger` — Telegram chat_id виходить за межі `int` для груп. Юзери поки в межах 32-біт, але краще одразу bigint.

`telegram_notifications_enabled` — для кнопки «Не нагадувати»: ставить у `false`, і команда нотифікацій пропускає.

---

## 📂 КРОК 8.3. Команда `vacancies:notify-expiring`

```bash
php artisan make:command NotifyExpiringVacanciesCommand
```

`app/Console/Commands/NotifyExpiringVacanciesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vacancy;
use App\Notifications\VacancyExpiringSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyExpiringVacanciesCommand extends Command
{
    protected $signature = 'vacancies:notify-expiring
                            {--hours=24 : За скільки годин до експайру повідомляти}
                            {--dry-run : Не відправляти, лише вивести список}
                            {--limit=100 : Максимум вакансій за один запуск (rate limit Telegram)}';

    protected $description = 'Надсилає роботодавцям сповіщення в Telegram про вакансії, які скоро завершаться';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $hours = (int) $this->option('hours');
        $isDryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        // Через scope з модуля 2 — тільки активні + expires_at у вікні + ще не повідомляли
        $vacancies = Vacancy::pendingExpiryNotification($hours)
            ->with('employer.user')  // підлаштуй під свою релейшн-структуру!
            ->limit($limit)
            ->get();

        if ($vacancies->isEmpty()) {
            $this->info('Немає вакансій, що потребують сповіщення.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($vacancies as $vacancy) {
            // Витягуємо роботодавця — підлаштуй під свою модель
            $user = $vacancy->employer?->user ?? $vacancy->user;

            // Скіпи з лог-причиною
            if (! $user) {
                Log::channel('vacancies')->warning('Notify-expiring: vacancy without user', [
                    'vacancy_id' => $vacancy->id,
                ]);
                $skipped++;
                continue;
            }

            if (! $user->telegram_chat_id) {
                Log::channel('vacancies')->info('Notify-expiring: user without telegram_chat_id', [
                    'vacancy_id' => $vacancy->id,
                    'user_id'    => $user->id,
                ]);
                $skipped++;
                continue;
            }

            if (! $user->telegram_notifications_enabled) {
                Log::channel('vacancies')->info('Notify-expiring: notifications disabled', [
                    'vacancy_id' => $vacancy->id,
                    'user_id'    => $user->id,
                ]);
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("[dry-run] Vacancy #{$vacancy->id} → user #{$user->id} (chat {$user->telegram_chat_id})");
                $sent++;
                continue;
            }

            try {
                // Через Laravel Notification з кастомним каналом 'telegram'
                $user->notify(new VacancyExpiringSoonNotification($vacancy));

                $vacancy->markExpiryNotificationSent();

                Log::channel('vacancies')->info('Notify-expiring: sent', [
                    'vacancy_id' => $vacancy->id,
                    'user_id'    => $user->id,
                    'chat_id'    => $user->telegram_chat_id,
                ]);
                $sent++;

                // Telegram rate limit: 30 повідомлень/секунду на бота.
                // На всяк випадок — 50ms між викликами (~20 msg/s).
                usleep(50_000);
            } catch (\Throwable $e) {
                Log::channel('vacancies')->error('Notify-expiring: failed', [
                    'vacancy_id' => $vacancy->id,
                    'user_id'    => $user->id,
                    'error'      => $e->getMessage(),
                ]);
                report($e);
                $failed++;
            }
        }

        $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);
        $this->info("Готово за {$elapsedMs} мс. Надіслано: {$sent}, пропущено: {$skipped}, помилки: {$failed}.");

        return self::SUCCESS;
    }
}
```

Зареєструй у `routes/console.php`:

```php
Schedule::command('vacancies:notify-expiring')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
```

---

## 📂 КРОК 8.4. Notification клас + Telegram канал

### 8.4.1. Notification

```bash
php artisan make:notification VacancyExpiringSoonNotification
```

`app/Notifications/VacancyExpiringSoonNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vacancy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class VacancyExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Vacancy $vacancy,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['telegram'];  // кастомний канал — нижче
    }

    public function toTelegram(object $notifiable): array
    {
        $title = $this->vacancy->title ?? "#{$this->vacancy->id}";
        $expiresAt = $this->vacancy->expires_at->locale('uk')->isoFormat('D MMMM, HH:mm');
        $vacancyId = $this->vacancy->id;

        return [
            'chat_id' => $notifiable->telegram_chat_id,
            'text' => <<<TEXT
            ⏰ <b>Вакансія завершиться завтра</b>

            «{$title}»
            ↳ Завершення: <b>{$expiresAt}</b>

            Хочете продовжити публікацію?
            TEXT,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '15 днів — 100 ₴', 'callback_data' => "vac:ext:{$vacancyId}:15"],
                        ['text' => '30 днів — 200 ₴', 'callback_data' => "vac:ext:{$vacancyId}:30"],
                    ],
                    [
                        ['text' => '90 днів — 500 ₴', 'callback_data' => "vac:ext:{$vacancyId}:90"],
                    ],
                    [
                        ['text' => '📦 Архівувати',  'callback_data' => "vac:arc:{$vacancyId}"],
                        ['text' => '🔕 Не нагадувати', 'callback_data' => "vac:mut:{$vacancyId}"],
                    ],
                ],
            ]),
        ];
    }
}
```

### 8.4.2. Кастомний Telegram-канал

`app/Notifications/Channels/TelegramChannel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use SergiX44\Nutgram\Nutgram;

class TelegramChannel
{
    public function __construct(private readonly Nutgram $bot) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $payload = $notification->toTelegram($notifiable);

        $this->bot->sendMessage(
            text: $payload['text'],
            chat_id: $payload['chat_id'],
            parse_mode: $payload['parse_mode'] ?? null,
            reply_markup: $payload['reply_markup'] ?? null,
        );
    }
}
```

Реєстрація каналу — `app/Providers/AppServiceProvider.php@boot`:

```php
use Illuminate\Notifications\ChannelManager;
use App\Notifications\Channels\TelegramChannel;

public function boot(): void
{
    Notification::extend('telegram', function ($app) {
        return $app->make(TelegramChannel::class);
    });
}
```

---

## 📂 КРОК 8.5. Обробники inline-кнопок

`routes/telegram.php` (або там, де у тебе налаштований Nutgram):

```php
use App\Models\Vacancy;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

$bot->onCallbackQueryData('vac:{action}:{id}', function (Nutgram $bot, string $action, string $id) {
    handleVacancyAction($bot, $action, (int) $id);
});

$bot->onCallbackQueryData('vac:{action}:{id}:{days}', function (Nutgram $bot, string $action, string $id, string $days) {
    handleVacancyAction($bot, $action, (int) $id, (int) $days);
});

function handleVacancyAction(Nutgram $bot, string $action, int $vacancyId, ?int $days = null): void
{
    // Авторизація: користувач повинен бути зв'язаний з Telegram
    $chatId = $bot->chatId();
    $user = User::where('telegram_chat_id', $chatId)->first();

    if (! $user) {
        $bot->answerCallbackQuery(text: '❌ Спочатку увійдіть на сайт через /start.', show_alert: true);
        return;
    }

    $vacancy = Vacancy::find($vacancyId);
    if (! $vacancy) {
        $bot->answerCallbackQuery(text: '❌ Вакансія не знайдена.', show_alert: true);
        return;
    }

    // Права: вакансія повинна належати юзеру
    $owner = $vacancy->employer?->user ?? $vacancy->user;
    if (! $owner || $owner->id !== $user->id) {
        $bot->answerCallbackQuery(text: '❌ У вас немає доступу до цієї вакансії.', show_alert: true);
        return;
    }

    match ($action) {
        'ext' => handleExtensionAction($bot, $vacancy, $days),
        'arc' => handleArchiveAction($bot, $vacancy),
        'mut' => handleMuteAction($bot, $vacancy, $user),
        default => $bot->answerCallbackQuery(text: '❌ Невідома дія.'),
    };
}

function handleExtensionAction(Nutgram $bot, Vacancy $vacancy, ?int $days): void
{
    if (! in_array($days, [15, 30, 90], true)) {
        $bot->answerCallbackQuery(text: '❌ Неприпустима кількість днів.', show_alert: true);
        return;
    }

    // Створюємо Stripe Checkout Session і даємо deep-link
    // (логіка створення сесії — у CheckoutService, поза цим модулем)
    /** @var \App\Services\Payments\CheckoutService $svc */
    $svc = app(\App\Services\Payments\CheckoutService::class);
    $url = $svc->createVacancyExtensionCheckout($vacancy, $days);

    $bot->editMessageText(
        text: "💳 Готово! Перейдіть для оплати:\n\n{$url}\n\nПісля оплати вакансія автоматично продовжиться.",
        reply_markup: null,  // прибираємо кнопки після кліку
    );
}

function handleArchiveAction(Nutgram $bot, Vacancy $vacancy): void
{
    $vacancy->archive();
    $bot->answerCallbackQuery(text: '✅ Вакансію архівовано.');
    $bot->editMessageText(
        text: "📦 Вакансію «{$vacancy->title}» переміщено в архів.",
        reply_markup: null,
    );
}

function handleMuteAction(Nutgram $bot, Vacancy $vacancy, User $user): void
{
    // Варіант A: вимкнути нагадування для всіх вакансій юзера
    // $user->update(['telegram_notifications_enabled' => false]);

    // Варіант B (рекомендую): тільки для цієї вакансії
    // потрібно поле `notify_expiry` у `vacancies` (можна додати або позначити через
    // expiry_notification_sent_at = далеке майбутнє як хак)
    $vacancy->update(['expiry_notification_sent_at' => now()]);

    $bot->answerCallbackQuery(text: '🔕 Нагадування вимкнено.');
    $bot->editMessageText(
        text: "🔕 Більше не нагадуватиму про цю вакансію.",
        reply_markup: null,
    );
}
```

---

## 📂 КРОК 8.6. Listener на `VacancyExtended` — інформувати про успіх

`app/Listeners/NotifyEmployerOfExtension.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VacancyExtended;
use Illuminate\Contracts\Queue\ShouldQueue;
use SergiX44\Nutgram\Nutgram;

class NotifyEmployerOfExtension implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly Nutgram $bot) {}

    public function handle(VacancyExtended $event): void
    {
        $owner = $event->vacancy->employer?->user ?? $event->vacancy->user;

        if (! $owner || ! $owner->telegram_chat_id) {
            return;
        }

        $title = $event->vacancy->title;
        $newExpiresAt = $event->vacancy->expires_at?->locale('uk')->isoFormat('D MMMM, HH:mm');

        $this->bot->sendMessage(
            chat_id: $owner->telegram_chat_id,
            text: <<<TEXT
            ✅ <b>Вакансію продовжено</b>

            «{$title}» активна до <b>{$newExpiresAt}</b>.
            Дякуємо!
            TEXT,
            parse_mode: 'HTML',
        );
    }
}
```

Реєстрація у `EventServiceProvider`:

```php
protected $listen = [
    \App\Events\VacancyExtended::class => [
        \App\Listeners\NotifyEmployerOfExtension::class,
    ],
];
```

---

## ⚠️ Критичні нюанси

### 1. Чому окрема команда, а не listener на якомусь event-і
Сповіщення «скоро завершиться» — це **proactive scan по часу**, а не реакція на дію. Немає event-у, на який ми могли б повісити listener: жодна дія не означає «зараз 24 години до завершення». Тому — scheduler.

### 2. Чому `expiry_notification_sent_at`, а не «надсилаємо завжди, хто отримає кілька разів — переживе»
- Користувач буде дратуватись (24 повідомлень для вакансії, що завершиться завтра).
- Telegram заблокує бота за спам.
- Виглядає непрофесійно.

Прапорець + scope `pendingExpiryNotification` — гарантія «один раз на цикл публікації». При `extend()` модель ресетить прапорець (модуль 2), тож наступне 24-годинне вікно знову спрацює.

### 3. Чому inline_keyboard, а не клавіатура під полем вводу
- `inline_keyboard` прив'язана до конкретного повідомлення — натискання не «загубиться» у потоці чату.
- Можна `editMessageText` після натискання — повідомлення оновлюється in-place, користувач бачить, що його клік був врахований.
- `reply_keyboard` показується для всіх повідомлень — заплутує, коли в чаті багато вакансій.

### 4. Формат `callback_data` обмежений 64 байтами
Telegram має ліміт 64 байти на `callback_data`. Тому я використовую короткі префікси `vac:ext:123:30`, а не `vacancy:extend:123:days=30`. **Не вкладай туди JSON.**

Якщо треба передати більше даних — зберігай у БД (`telegram_callback_payloads` таблиця) і посилайся на UUID.

### 5. Авторизація callback'ів
КРИТИЧНО: завжди перевіряй, що `telegram_chat_id → User → Vacancy.employer.user` ланцюжок цілий. Інакше зловмисник:
1. Запам'ятовує формат `vac:arc:{id}` з якогось публічного скріншота.
2. Створює свого бота з callback queries.
3. Архівує чужі вакансії.

Захист: перевіряй власника + сам бот має тільки той chat_id, який зареєстрований.

### 6. ShouldQueue з власною чергою
`onQueue('notifications')` — щоб критичні нотифікації не пхалися в один потік з важкими job-ами (індексація, генерація PDF). Окремий worker:

```bash
php artisan queue:work redis --queue=notifications --tries=3 --backoff=10
```

### 7. Rate limiting Telegram API
**Глобальний ліміт бота:** 30 повідомлень/секунду.
**Особистий чат:** 1 повідомлення/секунду.
**Груповий чат:** 20 повідомлень/хвилину.

`usleep(50_000)` між повідомленнями = 20 msg/s = у межах глобального ліміту, навіть якщо всі — у різні чати.

Якщо база виросте до 100+ нотифікацій за раз — переходь на queue jobs з `Bus::batch()` і delay.

### 8. Webhook чи polling для бота
- **Webhook:** Telegram сам пушить події на твій endpoint. Швидко, без затримок. Потрібен HTTPS.
- **Polling:** твій сервер регулярно опитує Telegram. Простіше для dev, гірше для прод.

Для My Job (production) — **тільки webhook**: `php artisan nutgram:hook:set`.

### 9. Помилки відправки = тимчасові
Якщо Telegram API недоступний — нотифікація NO-OP. **Не позначай** `expiry_notification_sent_at`, щоб наступний run scheduler-а спробував знову.

У моєму коді саме так: `markExpiryNotificationSent()` викликається ПІСЛЯ успішного `notify()`. Якщо `notify` кинув виняток — прапорець не виставлений.

### 10. Локалізація
Усі текстівки повідомлень — українською. Якщо колись додасте інші мови — переноси в `lang/uk/notifications/vacancy_expiring.php` через `__()`.

### 11. Тестування Nutgram
Nutgram має `Nutgram::fake()` для тестів:

```php
use SergiX44\Nutgram\Nutgram;

Nutgram::fake();

$user->notify(new VacancyExpiringSoonNotification($vacancy));

Nutgram::assertSent(fn ($update) => str_contains($update->message->text, 'завершиться завтра'));
```

Тестуй у модулі 10.

---

## 🧪 КРОК 8.7. Перевірка

```bash
# 1. Створити тестову вакансію, що завершиться через 23 години
php artisan tinker
```
```php
$user = \App\Models\User::factory()->create([
    'telegram_chat_id' => 123456789,  // твій реальний chat_id для тесту!
    'telegram_notifications_enabled' => true,
]);

$v = \App\Models\Vacancy::factory()->create([
    'employer_id' => $user->employer->id,  // підлаштуй під свою структуру
    'status' => 'active',
    'published_at' => now()->subDays(29),
    'expires_at' => now()->addHours(23),
    'expiry_notification_sent_at' => null,
]);
```

```bash
# 2. Dry-run
php artisan vacancies:notify-expiring --dry-run
# Очікуване: "[dry-run] Vacancy #X → user #Y (chat 123456789)"

# 3. Реальний запуск (отримаєш повідомлення в Telegram)
php artisan vacancies:notify-expiring

# 4. Натисни кнопку «30 днів — 200 ₴»
# Очікуване: повідомлення оновлюється з посиланням на Stripe checkout

# 5. Перевір БД
php artisan tinker --execute="dump(\App\Models\Vacancy::find($v->id)->expiry_notification_sent_at);"
# Очікуване: timestamp близько now()

# 6. Запусти команду ще раз — повідомлення НЕ повинно надійти (захист від дублів)
php artisan vacancies:notify-expiring
# Очікуване: "Немає вакансій, що потребують сповіщення."
```

---

## ✅ Очікуваний результат модуля

1. Міграція `telegram_chat_id` (якщо була потрібна).
2. Команда `vacancies:notify-expiring` з прапорцями.
3. Notification + кастомний Telegram канал.
4. Обробники inline-кнопок (extend / archive / mute).
5. Listener `NotifyEmployerOfExtension` на `VacancyExtended`.
6. Scheduler-реєстрація.
7. Ручний тест пройдено.
8. Звіт мені:
   ```
   Nutgram-нотифікації готові:
   - Команда vacancies:notify-expiring (hourly)
   - 4 callback handler-и: ext (з оплатою), arc, mut, default
   - Listener на VacancyExtended → дякуємо за оплату
   - Захист від дублів через expiry_notification_sent_at
   - Авторизація callback'ів через chat_id → User → Vacancy

   Перейти до модуля 9 (SEO-сторінка expired)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не вкладай JSON у `callback_data` — обмеження 64 байти.
- ❌ Не запускай команду частіше ніж щогодини — користувач отримає 24 повідомлення.
- ❌ Не пропускай авторизацію callback'ів — вразливість.
- ❌ Не відправляй нотифікації синхронно з webhook'у — `ShouldQueue` обов'язково.
- ❌ Не додавай logic створення Stripe checkout у цьому модулі — лише виклик сервісу.
- ❌ Не ставтесь до Telegram API як до 100% надійного — `try/catch` + retry через queue.
-e 

---


# МОДУЛЬ 9. SEO для expired-вакансій

## 🎯 Мета
Завершені вакансії залишаються доступні за прямим URL, але:
- `<meta name="robots" content="noindex, follow">` — не індексується новими краулерами
- Видимий банер «Вакансія неактивна» з CTA на схожі активні
- Schema.org `JobPosting` з валідним `validThrough`
- Архівовані повертають **404** (повністю прибрані з пошуку)

**Передумова:** модулі 1–4, 5 виконано.

---

## 🔍 Розвідка

```bash
# Поточний контролер вакансій
find app/Http/Controllers -name "*Vacanc*"
find app/Livewire -name "*Vacanc*" 2>/dev/null

# Поточна route
php artisan route:list | grep vacanc
```

---

## 📂 Контролер

`app/Http/Controllers/VacancyController.php` — метод `show`:

```php
use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use App\Services\Vacancies\SimilarVacanciesService;

public function show(string $slug, SimilarVacanciesService $similarSvc)
{
    $vacancy = Vacancy::where('slug', $slug)->firstOrFail();

    // Архівована — повне 404
    if ($vacancy->status === VacancyStatus::Archived) {
        abort(404);
    }

    // Чернетка — лише автору
    if ($vacancy->status === VacancyStatus::Draft && auth()->id() !== $vacancy->user_id) {
        abort(404);
    }

    $similar = $vacancy->status === VacancyStatus::Expired
        ? $similarSvc->findFor($vacancy, limit: 6)
        : collect();

    return view('vacancies.show', [
        'vacancy' => $vacancy,
        'similar' => $similar,
        'isExpired' => $vacancy->status === VacancyStatus::Expired,
    ]);
}
```

---

## 🧩 SimilarVacanciesService

`app/Services/Vacancies/SimilarVacanciesService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Vacancies;

use App\Models\Vacancy;
use Illuminate\Support\Collection;

class SimilarVacanciesService
{
    /**
     * Шукає схожі активні вакансії: ту саму категорію + місто, fallback — категорія.
     */
    public function findFor(Vacancy $vacancy, int $limit = 6): Collection
    {
        $query = Vacancy::active()
            ->where('id', '!=', $vacancy->id);

        // Фільтр: та сама категорія
        if ($vacancy->category_id) {
            $query->where('category_id', $vacancy->category_id);
        }

        // Бонус: те саме місто
        if ($vacancy->city_id) {
            $query->orderByRaw('CASE WHEN city_id = ? THEN 0 ELSE 1 END', [$vacancy->city_id]);
        }

        return $query
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
```

---

## 🎨 Blade-шаблон

`resources/views/vacancies/show.blade.php`:

```blade
<x-app-layout>
    @push('head')
        @if($isExpired)
            {{-- noindex, follow — не індексувати, але переходити по лінкам --}}
            <meta name="robots" content="noindex, follow">
        @endif

        {{-- Schema.org JobPosting --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'JobPosting',
                'title' => $vacancy->title,
                'description' => strip_tags($vacancy->description),
                'datePosted' => $vacancy->published_at?->toIso8601String(),
                'validThrough' => $vacancy->expires_at?->toIso8601String(),
                'employmentType' => $vacancy->employment_type ?? 'FULL_TIME',
                'hiringOrganization' => [
                    '@type' => 'Organization',
                    'name' => $vacancy->employer->name ?? 'Роботодавець',
                ],
                'jobLocation' => $vacancy->city ? [
                    '@type' => 'Place',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $vacancy->city->name,
                        'addressCountry' => 'UA',
                    ],
                ] : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush

    @if($isExpired)
        <x-vacancy.expired-banner :vacancy="$vacancy" />
    @endif

    <article class="prose @if($isExpired) opacity-75 @endif">
        <h1>{{ $vacancy->title }}</h1>
        <div>{!! $vacancy->description !!}</div>
        {{-- ... інший вміст --}}
    </article>

    @if($isExpired && $similar->isNotEmpty())
        <section class="mt-12 border-t pt-8">
            <h2 class="text-xl font-semibold mb-6">Схожі активні вакансії</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($similar as $item)
                    <x-vacancy.card :vacancy="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-app-layout>
```

---

## 🧩 Blade-компонент банера

`resources/views/components/vacancy/expired-banner.blade.php`:

```blade
@props(['vacancy'])

<div class="rounded-lg bg-yellow-50 border border-yellow-200 p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div class="flex-1">
            <h3 class="font-medium text-yellow-900">Ця вакансія неактивна</h3>
            <p class="text-sm text-yellow-700 mt-1">
                Публікація закрита {{ $vacancy->expires_at?->locale('uk')->isoFormat('D MMMM YYYY') }}.
                Перегляньте схожі активні вакансії нижче.
            </p>
        </div>
    </div>
</div>
```

---

## ⚠️ Нюанси

1. **`noindex, follow`** (а не `noindex, nofollow`) — щоб краулери все ще ходили по лінкам у тілі вакансії, передаючи PageRank на схожі активні.

2. **`validThrough` обов'язково** — Google Jobs використовує це поле, щоб ховати expired з результатів. Без нього вакансія може показуватись як активна тижнями.

3. **404 для archived, а не 410** — `410 Gone` сигналить «не повертайся ніколи». `404` дає індексу шанс перевірити пізніше. Для archived доречний `410`, але `404` — простіший і безпечніший дефолт.

4. **Не редіректь з expired на лендинг категорії** — це втрачає історичний контекст і шкодить UX (юзер прийшов саме за цією вакансією).

5. **Sitemap.xml** — окремий питання. Expired НЕ повинні бути в sitemap. Це поза цим модулем.

---

## ✅ Результат

- Контролер обробляє три статуси (Active/Expired/Archived) різно.
- noindex для expired, 404 для archived.
- Schema.org JobPosting з validThrough.
- Банер + блок «Схожі активні».
- Перейти до модуля 10 (тести).
-e 

---


# МОДУЛЬ 10 (розгорнутий). Тести — PHPUnit + Laravel Dusk

## 🎯 Мета модуля
Покрити життєвий цикл вакансії тестами на трьох рівнях:
- **Unit** — чиста логіка моделі та enum (швидко, ізольовано).
- **Feature** — інтеграція з БД, командами, контролерами, Filament.
- **Browser (Dusk)** — реальний браузер для Livewire-компонентів і повних user flows.

**Передумова:** модулі 1–9 виконано.

---

## 🔍 КРОК 10.1. Розвідка тестового середовища

```bash
# 1. PHPUnit налаштований?
cat phpunit.xml | head -30

# 2. Чи використовується Pest замість/поверх PHPUnit?
composer show pestphp/pest 2>/dev/null

# 3. Dusk встановлено?
composer show laravel/dusk 2>/dev/null
ls tests/Browser/ 2>/dev/null

# 4. База для тестів
grep DB_DATABASE phpunit.xml || grep DB_DATABASE .env.testing

# 5. Чи є фабрика для Vacancy?
ls database/factories/ | grep -i vacanc
```

**Запитай мене:**
> 1. Pest чи PHPUnit-style? (далі покажу обидва, ти обереш)
> 2. Тестова БД — sqlite in-memory чи окремий MySQL/Postgres?
> 3. Dusk інстальовано — чи робимо це частиною модуля?

---

## 🏭 КРОК 10.2. Фабрика з states

`database/factories/VacancyFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

class VacancyFactory extends Factory
{
    protected $model = Vacancy::class;

    public function definition(): array
    {
        return [
            // ... наявні базові поля (title, description, employer_id ...)
            'status'                      => VacancyStatus::Draft,
            'published_at'                => null,
            'expires_at'                  => null,
            'expiry_notification_sent_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => VacancyStatus::Draft]);
    }

    public function active(int $daysLeft = 15): static
    {
        return $this->state([
            'status'       => VacancyStatus::Active,
            'published_at' => now()->subDays(30 - $daysLeft),
            'expires_at'   => now()->addDays($daysLeft),
        ]);
    }

    public function expired(int $daysAgo = 5): static
    {
        return $this->state([
            'status'       => VacancyStatus::Expired,
            'published_at' => now()->subDays(30 + $daysAgo),
            'expires_at'   => now()->subDays($daysAgo),
        ]);
    }

    public function expiringSoon(int $hours = 12): static
    {
        return $this->state([
            'status'       => VacancyStatus::Active,
            'published_at' => now()->subDays(29),
            'expires_at'   => now()->addHours($hours),
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status'       => VacancyStatus::Archived,
            'published_at' => now()->subDays(60),
            'expires_at'   => now()->subDays(30),
        ]);
    }
}
```

**Це базис для всіх тестів далі.** Без фабричних states тести стануть нечитаними.

---

## 🧪 КРОК 10.3. Unit-тести

### 10.3.1. `tests/Unit/UkrainianPluralTest.php`

Якщо плюралізацію винесли у public method (або Service) — тестуємо її окремо. Критично, бо тут найбільше шансів пропустити edge case.

```php
<?php

declare(strict_types=1);

use App\Models\Vacancy;
use Illuminate\Support\Carbon;

// Через рефлексію викликаємо приватний метод pluralizeUk
function pluralize(int $n): string
{
    $reflection = new ReflectionClass(Vacancy::class);
    $method = $reflection->getMethod('pluralizeUk');
    $method->setAccessible(true);
    return $method->invoke(null, $n, 'день', 'дні', 'днів');
}

dataset('plural_cases', [
    [0, 'днів'],
    [1, 'день'],
    [2, 'дні'],
    [3, 'дні'],
    [4, 'дні'],
    [5, 'днів'],
    [10, 'днів'],
    [11, 'днів'],   // КРИТИЧНО: 11 → "днів", не "дні"
    [12, 'днів'],
    [14, 'днів'],
    [15, 'днів'],
    [20, 'днів'],
    [21, 'день'],
    [22, 'дні'],
    [25, 'днів'],
    [101, 'день'],
    [111, 'днів'],
    [121, 'день'],
    [1000, 'днів'],
]);

test('українська плюралізація для днів', function (int $n, string $expected) {
    expect(pluralize($n))->toBe($expected);
})->with('plural_cases');
```

PHPUnit-варіант:

```php
class UkrainianPluralTest extends TestCase
{
    /**
     * @dataProvider pluralCasesProvider
     */
    public function test_ukrainian_plural_for_days(int $n, string $expected): void
    {
        $reflection = new ReflectionClass(Vacancy::class);
        $method = $reflection->getMethod('pluralizeUk');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(null, $n, 'день', 'дні', 'днів'));
    }

    public static function pluralCasesProvider(): array
    {
        return [
            [0, 'днів'], [1, 'день'], [2, 'дні'], [3, 'дні'], [4, 'дні'],
            [5, 'днів'], [11, 'днів'], [21, 'день'], [22, 'дні'], [111, 'днів'],
            [121, 'день'], [1000, 'днів'],
        ];
    }
}
```

### 10.3.2. `tests/Unit/Enums/VacancyStatusTest.php`

```php
<?php

use App\Enums\VacancyStatus;

test('усі статуси мають українські лейбли', function () {
    expect(VacancyStatus::Draft->label())->toBe('Чернетка');
    expect(VacancyStatus::Active->label())->toBe('Активна');
    expect(VacancyStatus::Expired->label())->toBe('Завершена');
    expect(VacancyStatus::Archived->label())->toBe('Архів');
});

test('options повертає масив для Filament Select', function () {
    $options = VacancyStatus::options();

    expect($options)->toBeArray()
        ->and($options)->toHaveKey('active', 'Активна')
        ->and(count($options))->toBe(4);
});

test('кожен статус має унікальний badgeClass', function () {
    $classes = collect(VacancyStatus::cases())->map(fn ($s) => $s->badgeClass())->all();
    expect($classes)->toBe(array_unique($classes));
});
```

---

## 🧪 КРОК 10.4. Feature-тести моделі

`tests/Feature/Models/VacancyLifecycleTest.php`:

```php
<?php

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2025-06-15 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

// === publish() ===

test('publish переводить чернетку в active з expires через 30 днів', function () {
    $v = Vacancy::factory()->draft()->create();

    $v->publish(30);

    expect($v->fresh())
        ->status->toBe(VacancyStatus::Active)
        ->published_at->toEqual(now())
        ->expires_at->toEqual(now()->addDays(30));
});

test('publish не перезаписує published_at якщо вже є', function () {
    $original = now()->subDays(60);
    $v = Vacancy::factory()->expired()->create(['published_at' => $original]);

    $v->publish(30);

    expect($v->fresh()->published_at->toIso8601String())
        ->toBe($original->toIso8601String());
});

test('publish архівованої вакансії кидає виняток', function () {
    $v = Vacancy::factory()->archived()->create();

    expect(fn () => $v->publish(30))
        ->toThrow(DomainException::class, 'архівовано');
});

// === extend() ===

test('extend додає дні до expires_at для активної', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();
    $oldExpires = $v->expires_at->copy();

    $v->extend(15);

    expect($v->fresh()->expires_at->toIso8601String())
        ->toBe($oldExpires->addDays(15)->toIso8601String());
});

test('extend expired вакансії робить її active з expires=now+days', function () {
    $v = Vacancy::factory()->expired(daysAgo: 10)->create();

    $v->extend(30);

    expect($v->fresh())
        ->status->toBe(VacancyStatus::Active)
        ->expires_at->toEqual(now()->addDays(30));   // НЕ +30 від старого, а від ЗАРАЗ
});

test('extend скидає expiry_notification_sent_at', function () {
    $v = Vacancy::factory()->active(daysLeft: 1)->create([
        'expiry_notification_sent_at' => now()->subHours(2),
    ]);

    $v->extend(30);

    expect($v->fresh()->expiry_notification_sent_at)->toBeNull();
});

test('extend не змінює published_at', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();
    $original = $v->published_at->copy();

    $v->extend(15);

    expect($v->fresh()->published_at->toIso8601String())
        ->toBe($original->toIso8601String());
});

test('extend draft вакансії кидає виняток', function () {
    $v = Vacancy::factory()->draft()->create();
    expect(fn () => $v->extend(30))->toThrow(DomainException::class);
});

test('extend archived вакансії кидає виняток', function () {
    $v = Vacancy::factory()->archived()->create();
    expect(fn () => $v->extend(30))->toThrow(DomainException::class);
});

// === days_left / countdown_label ===

test('days_left повертає правильне число для активної', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();
    expect($v->days_left)->toBe(5);
});

test('days_left повертає null для draft / archived', function () {
    expect(Vacancy::factory()->draft()->create()->days_left)->toBeNull();
    expect(Vacancy::factory()->archived()->create()->days_left)->toBeNull();
});

test('countdown_label для 1 дня — "Залишилось 1 день"', function () {
    $v = Vacancy::factory()->active(daysLeft: 1)->create();
    expect($v->countdown_label)->toBe('Залишилось 1 день');
});

test('countdown_label для 2 днів — "Залишилось 2 дні"', function () {
    $v = Vacancy::factory()->active(daysLeft: 2)->create();
    expect($v->countdown_label)->toBe('Залишилось 2 дні');
});

test('countdown_label для 11 днів — "Залишилось 11 днів"', function () {
    $v = Vacancy::factory()->active(daysLeft: 11)->create();
    expect($v->countdown_label)->toBe('Залишилось 11 днів');
});

test('countdown_label для expired — "Публікацію завершено"', function () {
    $v = Vacancy::factory()->expired()->create();
    expect($v->countdown_label)->toBe('Публікацію завершено');
});

// === Scopes ===

test('scope active повертає тільки активні з майбутнім expires_at', function () {
    Vacancy::factory()->active()->count(3)->create();
    Vacancy::factory()->draft()->count(2)->create();
    Vacancy::factory()->expired()->count(2)->create();
    Vacancy::factory()->archived()->count(1)->create();

    expect(Vacancy::active()->count())->toBe(3);
});

test('scope expiringSoon знаходить вакансії у вікні годин', function () {
    Vacancy::factory()->expiringSoon(hours: 12)->create();   // у вікні
    Vacancy::factory()->expiringSoon(hours: 23)->create();   // у вікні
    Vacancy::factory()->active(daysLeft: 5)->create();        // поза вікном
    Vacancy::factory()->expired()->create();                  // не active

    expect(Vacancy::expiringSoon(24)->count())->toBe(2);
});

test('scope pendingExpiryNotification виключає вже сповіщені', function () {
    Vacancy::factory()->expiringSoon()->create([
        'expiry_notification_sent_at' => now()->subHours(2),  // вже сповіщений
    ]);
    Vacancy::factory()->expiringSoon()->create([
        'expiry_notification_sent_at' => null,                // ще треба
    ]);

    expect(Vacancy::pendingExpiryNotification(24)->count())->toBe(1);
});
```

---

## 🧪 КРОК 10.5. Команди

`tests/Feature/Console/ExpireVacanciesCommandTest.php`:

```php
<?php

use App\Enums\VacancyStatus;
use App\Models\Vacancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('команда переводить прострочені active в expired', function () {
    $expiredOne = Vacancy::factory()->active()->create([
        'expires_at' => now()->subHours(2),
    ]);
    $expiredTwo = Vacancy::factory()->active()->create([
        'expires_at' => now()->subDays(1),
    ]);
    $stillActive = Vacancy::factory()->active(daysLeft: 5)->create();
    $alreadyExpired = Vacancy::factory()->expired()->create();

    $this->artisan('vacancies:expire')
        ->expectsOutputToContain('Завершено 2')
        ->assertSuccessful();

    expect($expiredOne->fresh()->status)->toBe(VacancyStatus::Expired);
    expect($expiredTwo->fresh()->status)->toBe(VacancyStatus::Expired);
    expect($stillActive->fresh()->status)->toBe(VacancyStatus::Active);  // не зачепили
    expect($alreadyExpired->fresh()->status)->toBe(VacancyStatus::Expired);
});

test('--dry-run не змінює БД', function () {
    $v = Vacancy::factory()->active()->create(['expires_at' => now()->subHour()]);

    $this->artisan('vacancies:expire', ['--dry-run' => true])
        ->assertSuccessful();

    expect($v->fresh()->status)->toBe(VacancyStatus::Active);  // без змін
});

test('команда нічого не робить, якщо немає прострочених', function () {
    Vacancy::factory()->active(daysLeft: 5)->count(3)->create();

    $this->artisan('vacancies:expire')
        ->expectsOutputToContain('Завершено 0')
        ->assertSuccessful();
});

test('команда обробляє великий обсяг через chunk', function () {
    Vacancy::factory()->active()->count(1500)->create([
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('vacancies:expire', ['--batch' => 100])
        ->expectsOutputToContain('Завершено 1500')
        ->assertSuccessful();

    expect(Vacancy::expired()->count())->toBe(1500);
});
```

`tests/Feature/Console/NotifyExpiringVacanciesCommandTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\VacancyExpiringSoonNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('команда надсилає сповіщення про вакансії, що завершаться через 24 год', function () {
    Notification::fake();

    $user = User::factory()->create([
        'telegram_chat_id' => 12345,
        'telegram_notifications_enabled' => true,
    ]);
    $v = Vacancy::factory()
        ->for($user)  // або через employer — підлаштуй
        ->expiringSoon(hours: 12)
        ->create();

    $this->artisan('vacancies:notify-expiring')->assertSuccessful();

    Notification::assertSentTo(
        $user,
        VacancyExpiringSoonNotification::class,
        fn ($n) => $n->vacancy->id === $v->id,
    );

    expect($v->fresh()->expiry_notification_sent_at)->not->toBeNull();
});

test('не надсилає двічі (захист від дублів)', function () {
    Notification::fake();

    $user = User::factory()->create(['telegram_chat_id' => 12345]);
    Vacancy::factory()->for($user)->expiringSoon()->create([
        'expiry_notification_sent_at' => now()->subHours(2),
    ]);

    $this->artisan('vacancies:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});

test('пропускає юзерів без telegram_chat_id', function () {
    Notification::fake();

    $user = User::factory()->create(['telegram_chat_id' => null]);
    Vacancy::factory()->for($user)->expiringSoon()->create();

    $this->artisan('vacancies:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});

test('пропускає юзерів з вимкненими нотифікаціями', function () {
    Notification::fake();

    $user = User::factory()->create([
        'telegram_chat_id' => 12345,
        'telegram_notifications_enabled' => false,
    ]);
    Vacancy::factory()->for($user)->expiringSoon()->create();

    $this->artisan('vacancies:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});
```

---

## 🧪 КРОК 10.6. Stripe Webhook

`tests/Feature/Stripe/WebhookExtendsVacancyTest.php`:

Найскладніший тест, бо потрібно фейкати Stripe signature. Використовуємо хелпер:

```php
<?php

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeStripeWebhookPayload(string $eventType, array $data, ?string $eventId = null): array
{
    return [
        'id' => $eventId ?? 'evt_test_' . uniqid(),
        'object' => 'event',
        'type' => $eventType,
        'data' => ['object' => $data],
        'created' => time(),
    ];
}

function signStripePayload(array $payload, string $secret): array
{
    $body = json_encode($payload);
    $timestamp = time();
    $signedPayload = "{$timestamp}.{$body}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return [
        'body' => $body,
        'header' => "t={$timestamp},v1={$signature}",
    ];
}

beforeEach(function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
});

test('checkout.session.completed продовжує вакансію', function () {
    Event::fake();

    $vacancy = Vacancy::factory()->active(daysLeft: 1)->create();
    $oldExpires = $vacancy->expires_at->copy();

    $payload = makeStripeWebhookPayload('checkout.session.completed', [
        'id' => 'cs_test_123',
        'payment_status' => 'paid',
        'amount_total' => 20000,
        'currency' => 'uah',
        'metadata' => [
            'type' => 'vacancy_extension',
            'vacancy_id' => (string) $vacancy->id,
            'days' => '30',
        ],
    ]);
    $signed = signStripePayload($payload, config('services.stripe.webhook_secret'));

    $this->postJson(
        '/webhooks/stripe',
        json_decode($signed['body'], true),
        ['Stripe-Signature' => $signed['header']],
    )->assertOk();

    expect($vacancy->fresh())
        ->status->toBe(VacancyStatus::Active)
        ->expires_at->toEqual($oldExpires->addDays(30));

    Event::assertDispatched(VacancyExtended::class, fn ($e) =>
        $e->vacancy->id === $vacancy->id && $e->days === 30
    );
});

test('дублікат event_id ігнорується', function () {
    $vacancy = Vacancy::factory()->active()->create();

    $payload = makeStripeWebhookPayload('checkout.session.completed', [
        'id' => 'cs_test_dup',
        'payment_status' => 'paid',
        'metadata' => [
            'type' => 'vacancy_extension',
            'vacancy_id' => (string) $vacancy->id,
            'days' => '30',
        ],
    ], eventId: 'evt_duplicate_123');

    $signed = signStripePayload($payload, config('services.stripe.webhook_secret'));

    // Перший раз — обробляємо
    $this->postJson('/webhooks/stripe', json_decode($signed['body'], true), [
        'Stripe-Signature' => $signed['header'],
    ])->assertOk();

    $firstExpires = $vacancy->fresh()->expires_at->copy();

    // Другий раз — ігноруємо (інакше було б +60 днів)
    // Підпис ТОЙ САМИЙ → треба перепідписати з новим timestamp
    $signed2 = signStripePayload($payload, config('services.stripe.webhook_secret'));
    $this->postJson('/webhooks/stripe', json_decode($signed2['body'], true), [
        'Stripe-Signature' => $signed2['header'],
    ])
        ->assertOk()
        ->assertJson(['status' => 'duplicate']);

    expect($vacancy->fresh()->expires_at->toIso8601String())
        ->toBe($firstExpires->toIso8601String());  // не змінено
});

test('невалідний підпис повертає 400', function () {
    $this->postJson('/webhooks/stripe', ['fake' => 'data'], [
        'Stripe-Signature' => 'invalid',
    ])->assertStatus(400);
});

test('подія без metadata.type ігнорується', function () {
    Event::fake();

    $payload = makeStripeWebhookPayload('checkout.session.completed', [
        'id' => 'cs_no_meta',
        'payment_status' => 'paid',
        'metadata' => ['something' => 'else'],
    ]);
    $signed = signStripePayload($payload, config('services.stripe.webhook_secret'));

    $this->postJson('/webhooks/stripe', json_decode($signed['body'], true), [
        'Stripe-Signature' => $signed['header'],
    ])->assertOk();

    Event::assertNotDispatched(VacancyExtended::class);
});

test('archived вакансію не продовжуємо (refund-задача логується)', function () {
    Event::fake();

    $vacancy = Vacancy::factory()->archived()->create();

    $payload = makeStripeWebhookPayload('checkout.session.completed', [
        'id' => 'cs_archived',
        'payment_status' => 'paid',
        'amount_total' => 20000,
        'metadata' => [
            'type' => 'vacancy_extension',
            'vacancy_id' => (string) $vacancy->id,
            'days' => '30',
        ],
    ]);
    $signed = signStripePayload($payload, config('services.stripe.webhook_secret'));

    $this->postJson('/webhooks/stripe', json_decode($signed['body'], true), [
        'Stripe-Signature' => $signed['header'],
    ])->assertOk();

    expect($vacancy->fresh()->status)->toBe(VacancyStatus::Archived);  // без змін
    Event::assertNotDispatched(VacancyExtended::class);
});
```

---

## 🧪 КРОК 10.7. Filament

`tests/Feature/Filament/VacancyResourceTest.php`:

```php
<?php

use App\Enums\VacancyStatus;
use App\Filament\Resources\VacancyResource;
use App\Models\User;
use App\Models\Vacancy;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());  // підлаштуй під свою auth-структуру
});

test('таблиця показує статус як бейдж', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();

    Livewire::test(VacancyResource\Pages\ListVacancies::class)
        ->assertCanSeeTableRecords([$v])
        ->assertTableColumnFormattedStateSet('status', $v->status->label(), record: $v);
});

test('action extend_30 додає 30 днів до expires_at', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();
    $oldExpires = $v->expires_at->copy();

    Livewire::test(VacancyResource\Pages\ListVacancies::class)
        ->callTableAction('extend_30', $v)
        ->assertHasNoTableActionErrors();

    expect($v->fresh()->expires_at->toIso8601String())
        ->toBe($oldExpires->addDays(30)->toIso8601String());
});

test('action archive переводить у архів', function () {
    $v = Vacancy::factory()->active()->create();

    Livewire::test(VacancyResource\Pages\ListVacancies::class)
        ->callTableAction('archive', $v)
        ->assertHasNoTableActionErrors();

    expect($v->fresh()->status)->toBe(VacancyStatus::Archived);
});

test('bulk archive працює на кількох записах', function () {
    $vacancies = Vacancy::factory()->active()->count(3)->create();

    Livewire::test(VacancyResource\Pages\ListVacancies::class)
        ->callTableBulkAction('archive_bulk', $vacancies);

    foreach ($vacancies as $v) {
        expect($v->fresh()->status)->toBe(VacancyStatus::Archived);
    }
});

test('фільтр expiring_soon показує тільки потрібні', function () {
    $expiringSoon = Vacancy::factory()->expiringSoon(48)->create();
    $stillFar = Vacancy::factory()->active(daysLeft: 30)->create();

    Livewire::test(VacancyResource\Pages\ListVacancies::class)
        ->filterTable('expiring_soon')
        ->assertCanSeeTableRecords([$expiringSoon])
        ->assertCanNotSeeTableRecords([$stillFar]);
});
```

---

## 🧪 КРОК 10.8. Livewire countdown

`tests/Feature/Livewire/VacancyCountdownTest.php`:

```php
<?php

use App\Models\Vacancy;
use App\Livewire\VacancyCountdown;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('показує "Залишилось 3 дні" для вакансії з 3 днями', function () {
    $v = Vacancy::factory()->active(daysLeft: 3)->create();

    Livewire::test(VacancyCountdown::class, ['vacancy' => $v])
        ->assertSee('Залишилось 3 дні')
        ->assertSee('Активна');
});

test('показує "1 день" коли залишилось рівно 1', function () {
    $v = Vacancy::factory()->active(daysLeft: 1)->create();

    Livewire::test(VacancyCountdown::class, ['vacancy' => $v])
        ->assertSee('Залишилось 1 день')
        ->assertDontSee('1 дні')
        ->assertDontSee('1 днів');
});

test('expired показує кнопку поновлення', function () {
    $v = Vacancy::factory()->expired()->create();

    Livewire::test(VacancyCountdown::class, ['vacancy' => $v])
        ->assertSee('Публікацію завершено')
        ->assertSee('Поновити публікацію');
});

test('refresh оновлює стан після зміни в БД', function () {
    $v = Vacancy::factory()->active(daysLeft: 5)->create();

    $component = Livewire::test(VacancyCountdown::class, ['vacancy' => $v])
        ->assertSee('Залишилось 5 днів');

    // Змінюємо в БД ззовні
    $v->update(['expires_at' => now()->addDays(2)]);

    $component->call('refresh')->assertSee('Залишилось 2 дні');
});
```

---

## 🌐 КРОК 10.9. Dusk (Browser tests)

Якщо Dusk ще не встановлено:

```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

`tests/Browser/VacancyCountdownBrowserTest.php`:

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Vacancy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class VacancyCountdownBrowserTest extends DuskTestCase
{
    public function test_countdown_polls_and_updates_in_browser(): void
    {
        $user = User::factory()->create();
        $vacancy = Vacancy::factory()->for($user)->active(daysLeft: 3)->create();

        $this->browse(function (Browser $browser) use ($user, $vacancy) {
            $browser->loginAs($user)
                ->visit("/employer/vacancies/{$vacancy->id}")
                ->assertSee('Залишилось 3 дні')
                ->assertSee('Активна')
                ->assertPresent('[wire\\:poll]');
        });
    }

    public function test_expired_vacancy_shows_renewal_button(): void
    {
        $user = User::factory()->create();
        $vacancy = Vacancy::factory()->for($user)->expired()->create();

        $this->browse(function (Browser $browser) use ($user, $vacancy) {
            $browser->loginAs($user)
                ->visit("/employer/vacancies/{$vacancy->id}")
                ->assertSee('Публікацію завершено')
                ->assertSeeIn('a, button', 'Поновити публікацію');
        });
    }
}
```

`tests/Browser/ExpiredVacancyPageTest.php`:

```php
<?php

namespace Tests\Browser;

use App\Models\Vacancy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExpiredVacancyPageTest extends DuskTestCase
{
    public function test_expired_vacancy_shows_banner_and_noindex(): void
    {
        $vacancy = Vacancy::factory()->expired()->create();

        $this->browse(function (Browser $browser) use ($vacancy) {
            $browser->visit("/vacancies/{$vacancy->slug}")
                ->assertSee('Ця вакансія неактивна')
                ->assertSourceHas('<meta name="robots" content="noindex, follow">')
                ->assertSee('Схожі активні вакансії');
        });
    }

    public function test_archived_vacancy_returns_404(): void
    {
        $vacancy = Vacancy::factory()->archived()->create();

        $this->browse(function (Browser $browser) use ($vacancy) {
            $browser->visit("/vacancies/{$vacancy->slug}")
                ->assertSee('404');  // або інший маркер з твоєї 404-сторінки
        });
    }
}
```

---

## ⚙️ КРОК 10.10. CI / запуск

`composer.json` — додай скрипти:

```json
"scripts": {
    "test": [
        "@php artisan config:clear --ansi",
        "@php artisan test --parallel"
    ],
    "test:unit": "@php artisan test --testsuite=Unit",
    "test:feature": "@php artisan test --testsuite=Feature",
    "test:dusk": "@php artisan dusk",
    "test:ats": "@php artisan test --filter='Vacancy|VacancyStatus|UkrainianPlural|Stripe|Notify'"
}
```

Запуск:

```bash
composer test                  # усе крім Dusk
composer test:dusk             # окремо браузерні
composer test:ats              # тільки ATS-related
```

---

## ⚠️ Критичні нюанси тестування

### 1. `Carbon::setTestNow()` у `beforeEach`
Без фіксованого часу тест «через 30 днів» буде періодично падати на наносекундних різницях. Завжди фіксуй `now()` на старті тесту, а в кінці — `Carbon::setTestNow()` (без аргументу) скидає.

### 2. `RefreshDatabase` чи `DatabaseTransactions`?
- `RefreshDatabase`: повністю мігрує перед першим тестом, далі transactions. Швидко.
- `DatabaseTransactions`: вимагає, щоб БД уже була мігрована. Не чистить дані з попередніх runs.

Для CI — `RefreshDatabase`. Для локального swap — `DatabaseTransactions` (але тоді — обов'язково `php artisan migrate:fresh` перед першим запуском).

### 3. `Notification::fake()` vs реальна відправка
**У тестах** — завжди `Notification::fake()`. Інакше при кожному `php artisan test` твій телеграм буде сповіщатися реальними повідомленнями. У production CI — це зливає ключі та призводить до бану бота.

### 4. Stripe тести — підписи
Я навмисно показав, як підписати payload своїм секретом замість Stripe SDK. Це швидше і не вимагає мережі. Для exhaustive тестування — окремий test через `stripe-mock` (Docker-контейнер від Stripe), але це overkill для більшості кейсів.

### 5. Dusk — повільний, бережіть юніти
Один Dusk-тест = 5-15 секунд. Один PHPUnit = 10-100 мс. Тому:
- Усю **логіку** тестуй в Unit/Feature.
- В Dusk залишай тільки **інтеграційні** сценарії, які НЕМОЖЛИВО перевірити без браузера (JS, polling, real DOM).

### 6. Тести Telegram callback'ів
Окремий рівень складності. Nutgram надає `Nutgram::fake()`, але виклики `bot->sendMessage()` усередині listeners тестуються через спостереження за чергою:

```php
Queue::fake();
event(new VacancyExtended(...));
Queue::assertPushed(\App\Listeners\NotifyEmployerOfExtension::class);
```

**Не намагайся** зробити end-to-end тест Telegram у Dusk — це окрема історія з Telegram Bot API testing framework.

### 7. Чому ReflectionClass для приватних методів
`pluralizeUk` — приватний static. Тестується через рефлексію. Альтернатива — винести в публічний клас `App\Support\UkrainianPlural`, але це ускладнює структуру моделі. Для одного методу — рефлексія ОК.

### 8. Coverage — ціль і антициль
Не женись за 100% coverage. Цільте на:
- **>90%** для моделі `Vacancy` і enum.
- **>80%** для команд і webhook'ів.
- **>50%** для Filament/Livewire (тут багато boilerplate).

100% покриття змушує тестувати геттери, що ніколи не ламаються — марна трата часу.

---

## ✅ Очікуваний результат модуля

1. Фабрика з 5 states.
2. Unit-тести: плюралізація + enum (~20 тестів).
3. Feature-тести моделі: lifecycle + scopes (~25 тестів).
4. Feature-тести команд: expire + notify (~10 тестів).
5. Feature-тести Stripe webhook (~5 тестів).
6. Feature-тести Filament (~5 тестів).
7. Feature-тести Livewire countdown (~5 тестів).
8. Browser/Dusk-тести (~3-5 тестів).
9. composer scripts.
10. Звіт мені:
    ```
    Тестове покриття:
    - Unit: 20 пройдено
    - Feature/Models: 25 пройдено
    - Feature/Console: 10 пройдено
    - Feature/Stripe: 5 пройдено
    - Feature/Filament: 5 пройдено
    - Feature/Livewire: 5 пройдено
    - Browser/Dusk: 4 пройдено

    Загалом: 74 тести, 0 помилок, 312 assertions, 4.2с (без Dusk).

    ATS готовий до прод-релізу. Перевірити чек-лист завершення?
    ```

---

## 🚨 Чого НЕ робити

- ❌ Не запускай тести проти прод-БД. Перевір `DB_CONNECTION` у `phpunit.xml`.
- ❌ Не використовуй реальні Stripe ключі в тестах. Тільки `whsec_test_secret` placeholder.
- ❌ Не пиши тестів, які залежать від справжнього часу (`now()` без `setTestNow`).
- ❌ Не дублюй тести з Unit у Feature і Dusk — кожна логіка тестується на одному рівні.
- ❌ Не комітай `.env.testing` із чутливими даними.
- ❌ Не забудь додати тести у CI pipeline — інакше їх не буде запускатись на pull request'ах.
