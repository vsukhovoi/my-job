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
                                ->where('status', VacancyStatus::Active)  // double-check від race з webhook провайдера (MonoPay/Stripe/etc)
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

### 1. Race condition з webhook провайдера
**Сценарій:** клієнт натиснув «Продовжити», webhook (MonoPay / WayForPay / Stripe) стартував `extend()`, але ДО завершення транзакції scheduler знайшов цю саму вакансію (бо `expires_at` ще в минулому) і оновив на `expired`.

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
