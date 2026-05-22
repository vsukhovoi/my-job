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
