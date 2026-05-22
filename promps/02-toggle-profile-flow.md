# My Job — Промпт 2: Toggle + Profile Flow

## Контекст

Реалізуємо повний flow активації вакансії через Toggle з урахуванням
`ProfileCompletenessService`. Після збереження профілю компанії на 100% —
єдина вакансія роботодавця автоматично активується на 30 днів.

Стек: Laravel 13, Livewire 3 Volt, Filament, PHPUnit 12.
Конвенції: Enum-и, `#[Test]` атрибути, `actingAs()` перед `Volt::test()`.

Залежності:
- `ProfileCompletenessService` — вже реалізований, метод `getPercentage(User $user): int`
- `VacancyService::publish(Vacancy $vacancy)` — вже реалізований, встановлює `expires_at = now() + 30 днів`
- `VacancyStatus::Active`, `VacancyStatus::Draft` — Enum-и вже існують
- Modal #10 (`showProfileModal`) — вже існує в `dashboard.blade.php`

---

## Крок 1 — Оновити метод `toggleActive()` у dashboard Volt-компоненті

Знайди метод `toggleActive($vacancyId)` у
`resources/views/livewire/pages/employer/dashboard.blade.php`
або відповідному Volt class-файлі.

Замінити поточну логіку на:

```php
use App\Enums\VacancyStatus;
use App\Services\ProfileCompletenessService;
use App\Services\VacancyService;

public function toggleActive(int $vacancyId): void
{
    $vacancy = Vacancy::where('id', $vacancyId)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    // Деактивація — завжди дозволена
    if ($vacancy->status === VacancyStatus::Active) {
        $vacancy->forceFill([
            'status'    => VacancyStatus::Draft,
            'is_active' => false,
        ])->save();
        return;
    }

    // Активація — перевіряємо completeness
    $completeness = app(ProfileCompletenessService::class)
        ->getPercentage(auth()->user());

    if ($completeness < 100) {
        // Показуємо Modal #10
        $this->showProfileModal = true;
        return;
    }

    // Профіль заповнений — публікуємо на 30 днів
    app(VacancyService::class)->publish($vacancy);
}
```

---

## Крок 2 — Автоактивація після збереження профілю компанії

Знайди Volt-компонент профілю роботодавця:
`resources/views/livewire/employer/company-profile.blade.php`
або `resources/views/livewire/pages/employer/profile.blade.php`

У методі збереження (`save()` / `update()`) після успішного оновлення додай:

```php
use App\Models\Vacancy;
use App\Enums\VacancyStatus;
use App\Services\ProfileCompletenessService;
use App\Services\VacancyService;

// Після збереження даних компанії:
$completeness = app(ProfileCompletenessService::class)
    ->getPercentage(auth()->user());

if ($completeness === 100) {
    // Шукаємо єдину вакансію роботодавця (безкоштовний тариф = 1 вакансія)
    $vacancy = Vacancy::where('user_id', auth()->id())
        ->whereIn('status', [VacancyStatus::Draft, VacancyStatus::Expired])
        ->latest()
        ->first();

    if ($vacancy) {
        app(VacancyService::class)->publish($vacancy);

        session()->flash(
            'success',
            'Профіль збережено. Вакансію «' . $vacancy->title . '» активовано на 30 днів!'
        );

        $this->redirect(route('employer.vacancies'), navigate: true);
        return;
    }
}

// Стандартний flash якщо вакансії немає або вже активна
session()->flash('success', 'Профіль компанії збережено.');
```

---

## Крок 3 — Flash-повідомлення на сторінці вакансій

Переконайся що `resources/views/livewire/pages/employer/dashboard.blade.php`
або layout відображає flash `success`. Якщо ще немає — додай:

```blade
@if (session('success'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        class="mb-4 p-4 rounded-xl text-sm font-medium"
        style="background: #F0FDF4; color: #15803D; border: 1px solid #BBF7D0;">
        {{ session('success') }}
    </div>
@endif
```

---

## Крок 4 — Modal #10: додати контекст completeness

У `resources/views/livewire/pages/employer/dashboard.blade.php`
знайди Modal #10 (~рядок 226) і додай відображення `next_step`
з `ProfileCompletenessService` щоб користувач бачив що саме треба заповнити:

```blade
{{-- Всередині Modal #10, після підзаголовка --}}
@php
    $completeness = app(\App\Services\ProfileCompletenessService::class)
        ->getCompleteness(auth()->user());
@endphp

@if (!empty($completeness['next_step']))
    <div class="mt-3 p-3 rounded-lg text-sm text-left"
         style="background: #FEF3C7; border: 1px solid #FCD34D; color: #92400E;">
        <p class="font-medium">Що залишилось заповнити:</p>
        <p class="mt-1">{{ $completeness['next_step'] }}</p>
    </div>
@endif
```

> **Примітка:** використай той самий метод `getCompleteness()` або `getMissing()`
> який вже є в `ProfileCompletenessService` — не створюй новий.

---

## Крок 5 — Тести

Створи `tests/Feature/Employer/VacancyToggleTest.php`:

```php
<?php

namespace Tests\Feature\Employer;

use App\Enums\UserRole;
use App\Enums\VacancyStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ProfileCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VacancyToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;
    private Company $company;
    private Vacancy $vacancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->create(['role' => UserRole::Employer]);
        $this->company  = Company::factory()->create(['user_id' => $this->employer->id]);
        $this->vacancy  = Vacancy::factory()->create([
            'user_id' => $this->employer->id,
            'status'  => VacancyStatus::Draft,
        ]);
    }

    #[Test]
    public function toggle_shows_modal_when_profile_incomplete(): void
    {
        // ProfileCompletenessService повертає < 100
        $this->mock(ProfileCompletenessService::class, function ($mock) {
            $mock->shouldReceive('getPercentage')->andReturn(60);
        });

        $this->actingAs($this->employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $this->vacancy->id)
            ->assertSet('showProfileModal', true);

        // Вакансія залишається Draft
        $this->assertEquals(VacancyStatus::Draft, $this->vacancy->fresh()->status);
    }

    #[Test]
    public function toggle_activates_vacancy_when_profile_complete(): void
    {
        $this->mock(ProfileCompletenessService::class, function ($mock) {
            $mock->shouldReceive('getPercentage')->andReturn(100);
        });

        $this->actingAs($this->employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $this->vacancy->id)
            ->assertSet('showProfileModal', false);

        $this->assertEquals(VacancyStatus::Active, $this->vacancy->fresh()->status);
        $this->assertNotNull($this->vacancy->fresh()->expires_at);
    }

    #[Test]
    public function toggle_deactivates_active_vacancy_without_checking_profile(): void
    {
        $this->vacancy->update(['status' => VacancyStatus::Active]);

        // Навіть якщо профіль не заповнений — деактивація завжди працює
        $this->mock(ProfileCompletenessService::class, function ($mock) {
            $mock->shouldReceive('getPercentage')->never(); // не повинен викликатись
        });

        $this->actingAs($this->employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $this->vacancy->id);

        $this->assertEquals(VacancyStatus::Draft, $this->vacancy->fresh()->status);
    }

    #[Test]
    public function saving_complete_profile_activates_draft_vacancy(): void
    {
        $this->mock(ProfileCompletenessService::class, function ($mock) {
            $mock->shouldReceive('getPercentage')->andReturn(100);
            $mock->shouldReceive('getCompleteness')->andReturn([
                'percentage' => 100,
                'next_step'  => null,
                'missing'    => [],
            ]);
        });

        $this->actingAs($this->employer);

        Volt::test('pages.employer.profile')
            ->call('save')
            ->assertRedirect(route('employer.vacancies'));

        $this->assertEquals(VacancyStatus::Active, $this->vacancy->fresh()->status);
    }

    #[Test]
    public function saving_incomplete_profile_does_not_activate_vacancy(): void
    {
        $this->mock(ProfileCompletenessService::class, function ($mock) {
            $mock->shouldReceive('getPercentage')->andReturn(75);
            $mock->shouldReceive('getCompleteness')->andReturn([
                'percentage' => 75,
                'next_step'  => 'Додайте опис компанії',
                'missing'    => ['description'],
            ]);
        });

        $this->actingAs($this->employer);

        Volt::test('pages.employer.profile')
            ->call('save');

        $this->assertEquals(VacancyStatus::Draft, $this->vacancy->fresh()->status);
    }
}
```

```bash
php artisan test tests/Feature/Employer/VacancyToggleTest.php --stop-on-failure
```

---

## Чеклист

- [ ] `toggleActive()` перевіряє `ProfileCompletenessService::getPercentage()`
- [ ] `completeness < 100` → `showProfileModal = true`, вакансія не активується
- [ ] `completeness === 100` → `VacancyService::publish()`, вакансія активна 30 днів
- [ ] Деактивація (`Active → Draft`) — без перевірки профілю
- [ ] Після збереження профілю на 100% → єдина Draft/Expired вакансія активується
- [ ] Redirect → `employer.vacancies` з flash «активовано на 30 днів»
- [ ] Modal #10 показує `next_step` з `ProfileCompletenessService`
- [ ] 5/5 тестів зелені
