# My Job — Ручна верифікація компанії (адмін через Filament)

## Контекст

Автоматична перевірка через API реєстрів відкладена.
Адміністратор вручну перевіряє компанію на online.minjust.gov.ua і виставляє статус у Filament.
Архітектура готова до підключення реального API в майбутньому — поля в БД залишаються.

---

## Крок 1 — Enum CompanyVerificationStatus

Створи `app/Enums/CompanyVerificationStatus.php`:

```php
<?php

namespace App\Enums;

enum CompanyVerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified   = 'verified';
    case Rejected   = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Unverified => 'Не перевірено',
            self::Verified   => 'Верифіковано',
            self::Rejected   => 'Відхилено',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Unverified => 'gray',
            self::Verified   => 'success',
            self::Rejected   => 'danger',
        };
    }
}
```

---

## Крок 2 — Міграція

```bash
php artisan make:migration add_verification_to_companies_table
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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('verification_status')
                  ->default('unverified')
                  ->after('ipn');

            $table->string('verified_name')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verified_name');
            $table->foreignId('verified_by')->nullable()->after('verified_at')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_name', 'verified_at']);
        });
    }
};
```

```bash
php artisan migrate
```

---

## Крок 3 — Модель Company

Відкрий `app/Models/Company.php` і додай:

```php
use App\Enums\CompanyVerificationStatus;

// $fillable
'verification_status',
'verified_name',
'verified_at',
'verified_by',

// $casts
'verification_status' => CompanyVerificationStatus::class,
'verified_at'         => 'datetime',

// Хелпер
public function isVerified(): bool
{
    return $this->verification_status === CompanyVerificationStatus::Verified;
}
```

---

## Крок 4 — Filament CompanyResource

Відкрий `app/Filament/Resources/CompanyResource.php`.

### 4.1 — Колонка в таблиці

```php
use Filament\Tables\Columns\BadgeColumn;

BadgeColumn::make('verification_status')
    ->label('Верифікація')
    ->formatStateUsing(fn($state) => $state->label())
    ->colors([
        'gray'    => CompanyVerificationStatus::Unverified->value,
        'success' => CompanyVerificationStatus::Verified->value,
        'danger'  => CompanyVerificationStatus::Rejected->value,
    ]),
```

### 4.2 — Actions у таблиці

```php
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

// У ->actions([...]) таблиці:

Action::make('verify')
    ->label('Верифікувати')
    ->icon('heroicon-o-shield-check')
    ->color('success')
    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Verified)
    ->form([
        TextInput::make('verified_name')
            ->label('Офіційна назва (з реєстру)')
            ->default(fn($record) => $record->name)
            ->required(),
    ])
    ->action(function ($record, array $data): void {
        $record->update([
            'verification_status' => CompanyVerificationStatus::Verified,
            'verified_name'       => $data['verified_name'],
            'verified_at'         => now(),
            'verified_by'         => Auth::id(),
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Компанію верифіковано')
            ->success()
            ->send();
    }),

Action::make('reject')
    ->label('Відхилити')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('Відхилити верифікацію?')
    ->modalDescription('Статус компанії буде змінено на «Відхилено». Роботодавець отримає повідомлення.')
    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Rejected)
    ->action(function ($record): void {
        $record->update([
            'verification_status' => CompanyVerificationStatus::Rejected,
            'verified_name'       => null,
            'verified_at'         => null,
            'verified_by'         => null,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Верифікацію відхилено')
            ->danger()
            ->send();
    }),

Action::make('reset_verification')
    ->label('Скинути')
    ->icon('heroicon-o-arrow-path')
    ->color('gray')
    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Unverified)
    ->action(function ($record): void {
        $record->update([
            'verification_status' => CompanyVerificationStatus::Unverified,
            'verified_name'       => null,
            'verified_at'         => null,
            'verified_by'         => null,
        ]);
    }),
```

### 4.3 — Поля у формі перегляду (read-only)

```php
use Filament\Forms\Components\Placeholder;

Placeholder::make('verification_status')
    ->label('Статус верифікації')
    ->content(fn($record) => $record?->verification_status->label() ?? '—'),

Placeholder::make('verified_name')
    ->label('Назва в реєстрі')
    ->content(fn($record) => $record?->verified_name ?? '—'),

Placeholder::make('verified_at')
    ->label('Дата верифікації')
    ->content(fn($record) => $record?->verified_at?->format('d.m.Y H:i') ?? '—'),

Placeholder::make('verified_by')
    ->label('Перевірив')
    ->content(fn($record) => $record?->verifiedBy?->name ?? '—'),
```

Додай relation до моделі `Company`:

```php
public function verifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class, 'verified_by');
}
```

---

## Крок 5 — Бейдж у кабінеті роботодавця

У Volt-компоненті профілю компанії або на сторінці вакансії:

```blade
{{-- Статус верифікації для роботодавця --}}
@php $status = $company->verification_status; @endphp

@if ($status === \App\Enums\CompanyVerificationStatus::Verified)
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
        <x-heroicon-s-shield-check class="w-4 h-4" />
        Верифікована компанія
    </span>

@elseif ($status === \App\Enums\CompanyVerificationStatus::Rejected)
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
        <x-heroicon-s-x-circle class="w-4 h-4" />
        Верифікацію відхилено — зверніться до підтримки
    </span>

@else
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
        <x-heroicon-s-clock class="w-4 h-4" />
        Очікує верифікації
    </span>
@endif
```

Для здобувачів — на картці вакансії показувати лише верифіковані:

```blade
@if ($vacancy->company->isVerified())
    <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium">
        <x-heroicon-s-shield-check class="w-3.5 h-3.5" />
        Верифікована компанія
    </span>
@endif
```

---

## Крок 6 — Тести

Створи `tests/Feature/Employer/CompanyVerificationTest.php`:

```php
<?php

namespace Tests\Feature\Employer;

use App\Enums\CompanyVerificationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = User::factory()->create(['role' => UserRole::Admin]);
        $employer      = User::factory()->create(['role' => UserRole::Employer]);
        $this->company = Company::factory()->create([
            'user_id'             => $employer->id,
            'name'                => 'ТОВ "Тестова Компанія"',
            'verification_status' => CompanyVerificationStatus::Unverified,
        ]);
    }

    #[Test]
    public function company_defaults_to_unverified_status(): void
    {
        $this->assertEquals(
            CompanyVerificationStatus::Unverified,
            $this->company->verification_status
        );
        $this->assertFalse($this->company->isVerified());
    }

    #[Test]
    public function admin_can_verify_company(): void
    {
        $this->company->update([
            'verification_status' => CompanyVerificationStatus::Verified,
            'verified_name'       => 'ТОВАРИСТВО З ОБМЕЖЕНОЮ ВІДПОВІДАЛЬНІСТЮ "ТЕСТОВА КОМПАНІЯ"',
            'verified_at'         => now(),
            'verified_by'         => $this->admin->id,
        ]);

        $this->assertTrue($this->company->fresh()->isVerified());
        $this->assertDatabaseHas('companies', [
            'id'                  => $this->company->id,
            'verification_status' => 'verified',
            'verified_by'         => $this->admin->id,
        ]);
    }

    #[Test]
    public function admin_can_reject_company(): void
    {
        $this->company->update([
            'verification_status' => CompanyVerificationStatus::Rejected,
        ]);

        $this->assertEquals(
            CompanyVerificationStatus::Rejected,
            $this->company->fresh()->verification_status
        );
        $this->assertFalse($this->company->fresh()->isVerified());
    }

    #[Test]
    public function reset_clears_all_verification_fields(): void
    {
        $this->company->update([
            'verification_status' => CompanyVerificationStatus::Verified,
            'verified_name'       => 'Якась назва',
            'verified_at'         => now(),
            'verified_by'         => $this->admin->id,
        ]);

        $this->company->update([
            'verification_status' => CompanyVerificationStatus::Unverified,
            'verified_name'       => null,
            'verified_at'         => null,
            'verified_by'         => null,
        ]);

        $fresh = $this->company->fresh();
        $this->assertEquals(CompanyVerificationStatus::Unverified, $fresh->verification_status);
        $this->assertNull($fresh->verified_name);
        $this->assertNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
    }
}
```

```bash
php artisan test tests/Feature/Employer/CompanyVerificationTest.php --stop-on-failure
```

---

## Чеклист

- [ ] Enum `CompanyVerificationStatus` (3 статуси: unverified / verified / rejected)
- [ ] Міграція: `verification_status`, `verified_name`, `verified_at`, `verified_by`
- [ ] Модель `Company`: fillable, casts, `isVerified()`, `verifiedBy()` relation
- [ ] Filament: badge-колонка + 3 actions (Верифікувати / Відхилити / Скинути)
- [ ] Бейдж у кабінеті роботодавця (3 стани)
- [ ] Бейдж для здобувачів на картці вакансії
- [ ] 4/4 тести зелені
