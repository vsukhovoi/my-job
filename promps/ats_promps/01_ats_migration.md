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
