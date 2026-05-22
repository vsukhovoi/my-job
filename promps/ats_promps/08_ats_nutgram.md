# МОДУЛЬ 8 (розгорнутий). Nutgram — Telegram-сповіщення «вакансія скоро завершиться»

## 🎯 Мета модуля
За 24 години до завершення публікації Telegram-бот надсилає роботодавцю повідомлення з inline-кнопками: продовжити (на 15/30/90 днів), архівувати, заглушити нагадування. Натискання кнопок — без перекидання в браузер; усе обробляється в боті, з deep-link на оплату через активний провайдер (MonoPay / WayForPay / LiqPay / Stripe — залежно від `PAYMENT_GATEWAY`).

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
    // Namespace з модуля 11A (PaymentGateway абстракція)
    /** @var \App\Payments\CheckoutService $svc */
    $svc = app(\App\Payments\CheckoutService::class);
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
# Очікуване: повідомлення оновлюється з посиланням на checkout активного провайдера

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
- ❌ Не додавай logic створення checkout у цьому модулі — лише виклик `CheckoutService` (`App\Payments\CheckoutService` з модуля 11A).
- ❌ Не ставтесь до Telegram API як до 100% надійного — `try/catch` + retry через queue.
