# My Job — Промпт 3: Анонімна публікація вакансії

## Контекст

Додаємо тип публікації вакансії — анонімний (разова платна послуга).
Маршрут `extend` вже видалено (Промпт 1). Toggle flow вже реалізовано (Промпт 2).

Стек: Laravel 13, Livewire 3 Volt, Filament, PHPUnit 12.
Конвенції: Enum-и, `#[Test]` атрибути, `actingAs()` перед `Volt::test()`.

**Що отримує роботодавець при анонімній публікації:**
- Назва компанії прихована (показується псевдонім або «Компанія»)
- Вакансія не відображається у публічному профілі компанії
- Посилання на профіль компанії відсутнє
- `published_at` автоматично оновлюється щопонеділка (поки діє оплачений період)

---

## Крок 1 — Enum VacancyPublicationType

Створи `app/Enums/VacancyPublicationType.php`:

```php
<?php

namespace App\Enums;

enum VacancyPublicationType: string
{
    case Standard  = 'standard';
    case Anonymous = 'anonymous';

    public function label(): string
    {
        return match($this) {
            self::Standard  => 'Звичайна',
            self::Anonymous => 'Анонімна',
        };
    }
}
```

Додай до `GLOSSARY.md` у секцію Enum-ів:

```markdown
## Типи публікації вакансії (`App\Enums\VacancyPublicationType`)

| Enum | Value | Назва |
|------|-------|-------|
| `VacancyPublicationType::Standard`  | `standard`  | Звичайна |
| `VacancyPublicationType::Anonymous` | `anonymous` | Анонімна |
```

---

## Крок 2 — Міграція

```bash
php artisan make:migration add_anonymous_publication_to_vacancies_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('publication_type')
                  ->default('standard')
                  ->after('status');

            $table->string('anonymous_name')
                  ->nullable()
                  ->after('publication_type');

            $table->boolean('auto_refresh')
                  ->default(false)
                  ->after('anonymous_name');

            $table->timestamp('auto_refresh_until')
                  ->nullable()
                  ->after('auto_refresh');
        });
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn([
                'publication_type',
                'anonymous_name',
                'auto_refresh',
                'auto_refresh_until',
            ]);
        });
    }
};
```

```bash
php artisan migrate
```

---

## Крок 3 — Модель Vacancy

Відкрий `app/Models/Vacancy.php` і додай:

```php
use App\Enums\VacancyPublicationType;

// $fillable — додай:
'publication_type',
'anonymous_name',
'auto_refresh',
'auto_refresh_until',

// $casts — додай:
'publication_type'   => VacancyPublicationType::class,
'auto_refresh'       => 'boolean',
'auto_refresh_until' => 'datetime',

// Accessor — назва компанії для відображення
public function getDisplayCompanyNameAttribute(): string
{
    if ($this->publication_type === VacancyPublicationType::Anonymous) {
        return $this->anonymous_name ?? 'Компанія';
    }

    return $this->company?->name ?? 'Компанія';
}

// Хелпери
public function isAnonymous(): bool
{
    return $this->publication_type === VacancyPublicationType::Anonymous;
}

public function hasAutoRefresh(): bool
{
    return $this->auto_refresh
        && $this->auto_refresh_until
        && $this->auto_refresh_until->isFuture();
}
```

---

## Крок 4 — Scheduler: auto-refresh

### 4.1 — Artisan-команда

```bash
php artisan make:command RefreshAnonymousVacancies
```

```php
<?php

namespace App\Console\Commands;

use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Illuminate\Console\Command;

class RefreshAnonymousVacancies extends Command
{
    protected $signature   = 'vacancies:refresh-anonymous';
    protected $description = 'Щотижневе оновлення published_at для анонімних вакансій';

    public function handle(): void
    {
        $count = Vacancy::query()
            ->where('publication_type', VacancyPublicationType::Anonymous)
            ->where('status', VacancyStatus::Active)
            ->where('auto_refresh', true)
            ->where('auto_refresh_until', '>', now())
            ->update(['published_at' => now()]);

        $this->info("Оновлено вакансій: {$count}");
    }
}
```

### 4.2 — Розклад

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('vacancies:refresh-anonymous')
    ->weekly()
    ->mondays()
    ->at('06:00');
```

---

## Крок 5 — Публічна частина

### 5.1 — Картка вакансії

Знайди місце де відображається назва компанії і заміни на accessor:

```blade
@if (!$vacancy->isAnonymous())
    <a href="{{ route('employer.public-profile', $vacancy->company->slug) }}"
       class="text-sm text-gray-600 hover:text-orange-500 transition">
        {{ $vacancy->display_company_name }}
    </a>
@else
    <span class="text-sm text-gray-600">
        {{ $vacancy->display_company_name }}
    </span>
@endif
```

### 5.2 — Сторінка вакансії (`jobs/show.blade.php`)

Знайди блок компанії і заміни:

```blade
@if ($vacancy->isAnonymous())
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center"
             style="border: 1px solid #E5E7EB;">
            <x-heroicon-o-building-office class="w-6 h-6 text-gray-400" />
        </div>
        <div>
            <p class="font-semibold" style="color: #1F2937;">
                {{ $vacancy->display_company_name }}
            </p>
            <p class="text-sm" style="color: #6B7280;">Конфіденційний роботодавець</p>
        </div>
    </div>
@else
    <a href="{{ route('employer.public-profile', $vacancy->company->slug) }}"
       class="flex items-center gap-3 hover:opacity-80 transition">
        <img src="{{ $vacancy->company->logo_url }}"
             alt="{{ $vacancy->company->name }}"
             class="w-12 h-12 rounded-full object-cover" />
        <p class="font-semibold" style="color: #1F2937;">
            {{ $vacancy->company->name }}
        </p>
    </a>
@endif
```

### 5.3 — Публічний профіль компанії

У запиті вакансій для сторінки компанії додай фільтр:

```php
$vacancies = Vacancy::where('user_id', $company->user_id)
    ->where('status', VacancyStatus::Active)
    ->where('publication_type', VacancyPublicationType::Standard) // ← додай
    ->latest('published_at')
    ->get();
```

### 5.4 — SEO JobPostingSchema

У `JobPostingSchema` View Component:

```php
'hiringOrganization' => $vacancy->isAnonymous()
    ? [
        '@type' => 'Organization',
        'name'  => $vacancy->display_company_name,
    ]
    : [
        '@type'  => 'Organization',
        'name'   => $vacancy->company->name,
        'sameAs' => route('employer.public-profile', $vacancy->company->slug),
    ],
```

---

## Крок 6 — Modal #10: кнопка «Опублікувати анонімно»

Знайди Modal #10 у `dashboard.blade.php` (~рядок 226).
Додай третю кнопку між «Заповнити профіль» і «Пропустити»:

```blade
{{-- Існуюча кнопка --}}
<a href="{{ route('employer.profile') }}"
   class="w-full py-3 px-4 text-white font-semibold rounded-xl transition text-center block"
   style="background: #2563EB;">
    Заповнити профіль компанії
</a>

{{-- НОВА кнопка --}}
<a href="{{ route('employer.billing') }}"
   class="w-full py-3 px-4 font-semibold rounded-xl transition text-center block"
   style="border: 2px solid #F36F21; color: #F36F21; background: transparent;">
    Опублікувати анонімно
</a>

{{-- Існуюча кнопка --}}
<button @click="show = false"
        class="text-sm transition"
        style="color: #9CA3AF;">
    Пропустити
</button>
```

---

## Крок 7 — Форма створення/редагування вакансії

Знайди Volt-компонент форми вакансії. У `state()` додай:

```php
'publication_type' => $vacancy->publication_type?->value ?? 'standard',
'anonymous_name'   => $vacancy->anonymous_name ?? '',
```

У шаблоні після основних полів:

```blade
<div class="rounded-xl p-4" style="border: 1px solid #E5E7EB; background: #F9FAFB;">
    <h3 class="font-semibold mb-3" style="color: #1F2937;">Тип публікації</h3>

    <div class="space-y-3">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="radio" wire:model.live="publication_type" value="standard" class="mt-1" />
            <div>
                <p class="font-medium" style="color: #1F2937;">Звичайна — безкоштовно</p>
                <p class="text-sm" style="color: #6B7280;">
                    Назва та профіль компанії відображаються для кандидатів
                </p>
            </div>
        </label>

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="radio" wire:model.live="publication_type" value="anonymous" class="mt-1" />
            <div>
                <p class="font-medium" style="color: #1F2937;">Анонімна — платна послуга</p>
                <p class="text-sm" style="color: #6B7280;">
                    Бренд прихований. Автооновлення позиції щотижня.
                    Вакансія відсутня у списку вакансій компанії.
                </p>
            </div>
        </label>
    </div>

    @if ($publication_type === 'anonymous')
        <div class="mt-4">
            <label class="block text-sm font-medium mb-1" style="color: #374151;">
                Назва для відображення
            </label>
            <input type="text"
                   wire:model="anonymous_name"
                   placeholder="Компанія"
                   maxlength="100"
                   class="w-full px-3 py-2 text-sm"
                   style="border: 1px solid var(--input-border-color);
                          border-radius: var(--input-radius);
                          background: var(--input-bg);
                          color: var(--text-main);" />
            <p class="mt-1 text-xs" style="color: #6B7280;">
                Залиш порожнім — буде відображатись «Компанія»
            </p>
        </div>

        <div class="mt-4 p-3 rounded-lg text-sm"
             style="background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E;">
            ⚠ Анонімна публікація — платна послуга.
            Після збереження вас буде направлено до оплати.
        </div>
    @endif
</div>
```

У методі `save()`:

```php
$vacancy->update([
    'publication_type' => VacancyPublicationType::from($this->publication_type),
    'anonymous_name'   => $this->publication_type === 'anonymous'
        ? ($this->anonymous_name ?: null)
        : null,
]);

if ($this->publication_type === 'anonymous') {
    session(['anonymous_vacancy_id' => $vacancy->id]);
    $this->redirect(route('employer.billing'), navigate: true);
    return;
}
```

---

## Крок 8 — Filament VacancyResource

```php
use App\Enums\VacancyPublicationType;

// Колонка в таблиці:
BadgeColumn::make('publication_type')
    ->label('Тип')
    ->formatStateUsing(fn($state) => $state->label())
    ->colors([
        'gray'    => VacancyPublicationType::Standard->value,
        'warning' => VacancyPublicationType::Anonymous->value,
    ]),

TextColumn::make('anonymous_name')
    ->label('Псевдонім')
    ->default('—'),

IconColumn::make('auto_refresh')
    ->label('Авто-оновлення')
    ->boolean(),

TextColumn::make('auto_refresh_until')
    ->label('Авто-оновлення до')
    ->dateTime('d.m.Y')
    ->default('—'),
```

---

## Крок 9 — Тести

Створи `tests/Feature/Employer/AnonymousVacancyTest.php`:

```php
<?php

namespace Tests\Feature\Employer;

use App\Enums\UserRole;
use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnonymousVacancyTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;
    private Company $company;
    private Vacancy $vacancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->create(['role' => UserRole::Employer]);
        $this->company  = Company::factory()->create([
            'user_id' => $this->employer->id,
            'name'    => 'ТОВ "Тестова Компанія"',
        ]);
        $this->vacancy  = Vacancy::factory()->create([
            'user_id'          => $this->employer->id,
            'status'           => VacancyStatus::Active,
            'publication_type' => VacancyPublicationType::Standard,
        ]);
    }

    #[Test]
    public function vacancy_defaults_to_standard_publication_type(): void
    {
        $this->assertEquals(VacancyPublicationType::Standard, $this->vacancy->publication_type);
        $this->assertFalse($this->vacancy->isAnonymous());
    }

    #[Test]
    public function anonymous_vacancy_shows_default_name_when_no_pseudonym(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'anonymous_name'   => null,
        ]);

        $this->assertEquals('Компанія', $this->vacancy->fresh()->display_company_name);
    }

    #[Test]
    public function anonymous_vacancy_shows_custom_pseudonym(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'anonymous_name'   => 'Великий Бізнес',
        ]);

        $this->assertEquals('Великий Бізнес', $this->vacancy->fresh()->display_company_name);
    }

    #[Test]
    public function standard_vacancy_shows_real_company_name(): void
    {
        $this->assertEquals(
            $this->company->name,
            $this->vacancy->display_company_name
        );
    }

    #[Test]
    public function anonymous_vacancy_excluded_from_company_public_profile(): void
    {
        $this->vacancy->update(['publication_type' => VacancyPublicationType::Anonymous]);

        $publicVacancies = Vacancy::where('user_id', $this->employer->id)
            ->where('status', VacancyStatus::Active)
            ->where('publication_type', VacancyPublicationType::Standard)
            ->get();

        $this->assertCount(0, $publicVacancies);
    }

    #[Test]
    public function auto_refresh_command_updates_published_at(): void
    {
        $oldDate = now()->subDays(7);

        $this->vacancy->update([
            'publication_type'   => VacancyPublicationType::Anonymous,
            'auto_refresh'       => true,
            'auto_refresh_until' => now()->addDays(30),
            'published_at'       => $oldDate,
        ]);

        $this->artisan('vacancies:refresh-anonymous')->assertSuccessful();

        $this->assertGreaterThan(
            $oldDate,
            $this->vacancy->fresh()->published_at
        );
    }

    #[Test]
    public function expired_auto_refresh_does_not_update_vacancy(): void
    {
        $oldDate = now()->subDays(7);

        $this->vacancy->update([
            'publication_type'   => VacancyPublicationType::Anonymous,
            'auto_refresh'       => true,
            'auto_refresh_until' => now()->subDay(),
            'published_at'       => $oldDate,
        ]);

        $this->artisan('vacancies:refresh-anonymous')->assertSuccessful();

        $this->assertEquals(
            $oldDate->toDateTimeString(),
            $this->vacancy->fresh()->published_at->toDateTimeString()
        );
    }
}
```

```bash
php artisan test tests/Feature/Employer/AnonymousVacancyTest.php --stop-on-failure
```

---

## Порядок виконання

> Цей промпт виконується **після** `01-remove-extend.md` та `02-toggle-profile-flow.md`.

---

## Чеклист

- [ ] Enum `VacancyPublicationType` + запис у `GLOSSARY.md`
- [ ] Міграція: `publication_type`, `anonymous_name`, `auto_refresh`, `auto_refresh_until`
- [ ] Модель `Vacancy`: fillable, casts, `display_company_name`, `isAnonymous()`, `hasAutoRefresh()`
- [ ] Artisan `vacancies:refresh-anonymous` + scheduler щопонеділка 06:00
- [ ] Картка вакансії — без посилання на компанію для анонімних
- [ ] Сторінка вакансії — без бренду, логотипу, посилання
- [ ] Публічний профіль компанії — фільтр `publication_type = standard`
- [ ] `JobPostingSchema` — `hiringOrganization` без `sameAs`
- [ ] Modal #10 — третя кнопка «Опублікувати анонімно» → `employer.billing`
- [ ] Форма вакансії — вибір типу + псевдонім + редірект на білінг
- [ ] Filament VacancyResource — badge + колонки
- [ ] 7/7 тестів зелені
