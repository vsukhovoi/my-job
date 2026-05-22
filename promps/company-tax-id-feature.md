# My Job — Додавання ЄДРПОУ / ІПН до профілю компанії

## Контекст

Платформа My Job (Laravel 13, Livewire 3 Volt, Filament, PHPUnit 12).
Роботодавець може бути юридичною особою (має ЄДРПОУ — 8 цифр) або фізичною особою-підприємцем (має ІПН — 10 цифр).
Тип бізнесу вже може зберігатися в таблиці `companies` або `users` — перевір перед виконанням.

---

## Крок 1 — Перевір поточну структуру

```bash
php artisan db:show --json | grep -A5 companies
# або відкрий міграцію таблиці companies
```

Знайди файл моделі: `app/Models/Company.php`

---

## Крок 2 — Міграція

Створи нову міграцію:

```bash
php artisan make:migration add_tax_id_to_companies_table
```

Вміст міграції:

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
            // Тип підприємства: 'legal' = ТОВ/АТ/etc (ЄДРПОУ), 'individual' = ФОП (ІПН)
            $table->enum('business_type', ['legal', 'individual'])
                  ->default('legal')
                  ->after('name');

            // ЄДРПОУ: 8 цифр — для юридичних осіб
            $table->string('edrpou', 8)->nullable()->after('business_type');

            // ІПН: 10 цифр — для фізичних осіб-підприємців
            $table->string('ipn', 10)->nullable()->after('edrpou');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'edrpou', 'ipn']);
        });
    }
};
```

Виконай:

```bash
php artisan migrate
```

---

## Крок 3 — Enum BusinessType

Створи файл `app/Enums/BusinessType.php`:

```php
<?php

namespace App\Enums;

enum BusinessType: string
{
    case Legal     = 'legal';
    case Individual = 'individual';

    public function label(): string
    {
        return match($this) {
            self::Legal      => 'Юридична особа (ЄДРПОУ)',
            self::Individual => 'ФОП (ІПН)',
        };
    }

    public function taxIdLabel(): string
    {
        return match($this) {
            self::Legal      => 'ЄДРПОУ',
            self::Individual => 'ІПН',
        };
    }

    public function taxIdLength(): int
    {
        return match($this) {
            self::Legal      => 8,
            self::Individual => 10,
        };
    }
}
```

---

## Крок 4 — Модель Company

Відкрий `app/Models/Company.php` і додай:

```php
use App\Enums\BusinessType;

// У масиві $fillable додай:
'business_type',
'edrpou',
'ipn',

// У масиві $casts додай:
'business_type' => BusinessType::class,

// Додай accessor для зручності:
public function getTaxIdAttribute(): ?string
{
    return match($this->business_type) {
        BusinessType::Legal      => $this->edrpou,
        BusinessType::Individual => $this->ipn,
        default                  => null,
    };
}

public function getTaxIdLabelAttribute(): string
{
    return $this->business_type?->taxIdLabel() ?? 'Код';
}
```

---

## Крок 5 — Правила валідації

Створи або оновіть `app/Http/Requests/UpdateCompanyRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessType = $this->enum('business_type', BusinessType::class);

        return [
            'business_type' => ['required', Rule::enum(BusinessType::class)],

            'edrpou' => [
                Rule::when(
                    $businessType === BusinessType::Legal,
                    ['required', 'digits:8'],
                    ['nullable']
                ),
            ],

            'ipn' => [
                Rule::when(
                    $businessType === BusinessType::Individual,
                    ['required', 'digits:10'],
                    ['nullable']
                ),
            ],

            // Решта полів профілю компанії залиш як є
        ];
    }

    public function messages(): array
    {
        return [
            'edrpou.required' => 'ЄДРПОУ є обов'язковим для юридичних осіб.',
            'edrpou.digits'   => 'ЄДРПОУ повинен містити рівно 8 цифр.',
            'ipn.required'    => 'ІПН є обов'язковим для ФОП.',
            'ipn.digits'      => 'ІПН повинен містити рівно 10 цифр.',
        ];
    }
}
```

---

## Крок 6 — Volt-компонент профілю компанії

Знайди існуючий Volt-компонент редагування профілю компанії (наприклад `resources/views/livewire/employer/company-profile.blade.php`).

Додай у `state()` / `$state` (залежно від того, як реалізовано):

```php
'business_type' => $company->business_type?->value ?? 'legal',
'edrpou'        => $company->edrpou ?? '',
'ipn'           => $company->ipn ?? '',
```

У методі збереження — додай валідацію та збереження нових полів:

```php
$company->update([
    'business_type' => BusinessType::from($this->business_type),
    'edrpou'        => $this->business_type === 'legal' ? $this->edrpou : null,
    'ipn'           => $this->business_type === 'individual' ? $this->ipn : null,
]);
```

У шаблоні (blade) додай блок після назви компанії:

```blade
{{-- Тип підприємства --}}
<div>
    <x-label for="business_type" value="Тип підприємства" />
    <select wire:model.live="business_type" id="business_type"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
        <option value="legal">Юридична особа (ЄДРПОУ)</option>
        <option value="individual">ФОП (ІПН)</option>
    </select>
    <x-input-error for="business_type" class="mt-1" />
</div>

{{-- ЄДРПОУ (тільки для юридичних осіб) --}}
@if ($business_type === 'legal')
<div>
    <x-label for="edrpou" value="ЄДРПОУ" />
    <x-input id="edrpou" type="text" inputmode="numeric" maxlength="8"
              wire:model="edrpou" class="mt-1 block w-full"
              placeholder="12345678" />
    <x-input-error for="edrpou" class="mt-1" />
    <p class="mt-1 text-xs text-gray-500">8 цифр без пробілів</p>
</div>
@endif

{{-- ІПН (тільки для ФОП) --}}
@if ($business_type === 'individual')
<div>
    <x-label for="ipn" value="ІПН" />
    <x-input id="ipn" type="text" inputmode="numeric" maxlength="10"
              wire:model="ipn" class="mt-1 block w-full"
              placeholder="1234567890" />
    <x-input-error for="ipn" class="mt-1" />
    <p class="mt-1 text-xs text-gray-500">10 цифр без пробілів</p>
</div>
@endif
```

> **Важливо:** `wire:model.live` на `business_type` забезпечує реактивне приховування/показування полів без перезавантаження сторінки.

---

## Крок 7 — Filament ресурс (адмін-панель)

Знайди `app/Filament/Resources/CompanyResource.php` і додай у форму:

```php
use App\Enums\BusinessType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

// У Schema форми:
Select::make('business_type')
    ->label('Тип підприємства')
    ->options(collect(BusinessType::cases())->mapWithKeys(
        fn($case) => [$case->value => $case->label()]
    ))
    ->default('legal')
    ->live()
    ->required(),

TextInput::make('edrpou')
    ->label('ЄДРПОУ')
    ->numeric()
    ->length(8)
    ->placeholder('12345678')
    ->helperText('8 цифр — для юридичних осіб')
    ->visible(fn(Get $get) => $get('business_type') === 'legal')
    ->requiredIf('business_type', 'legal'),

TextInput::make('ipn')
    ->label('ІПН')
    ->numeric()
    ->length(10)
    ->placeholder('1234567890')
    ->helperText('10 цифр — для ФОП')
    ->visible(fn(Get $get) => $get('business_type') === 'individual')
    ->requiredIf('business_type', 'individual'),
```

Додай у таблицю (columns):

```php
TextColumn::make('tax_id_label')
    ->label('Тип / Код')
    ->getStateUsing(fn($record) => $record->tax_id_label . ': ' . ($record->tax_id ?? '—'))
    ->searchable(query: function ($query, $search) {
        $query->where('edrpou', 'like', "%{$search}%")
              ->orWhere('ipn', 'like', "%{$search}%");
    }),
```

---

## Крок 8 — Тести

Створи `tests/Feature/Employer/CompanyTaxIdTest.php`:

```php
<?php

namespace Tests\Feature\Employer;

use App\Enums\BusinessType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyTaxIdTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->create(['role' => UserRole::Employer]);
        $this->company  = Company::factory()->create(['user_id' => $this->employer->id]);
    }

    #[Test]
    public function legal_company_can_save_edrpou(): void
    {
        $this->company->update([
            'business_type' => BusinessType::Legal,
            'edrpou'        => '12345678',
            'ipn'           => null,
        ]);

        $this->assertDatabaseHas('companies', [
            'id'            => $this->company->id,
            'business_type' => 'legal',
            'edrpou'        => '12345678',
            'ipn'           => null,
        ]);
    }

    #[Test]
    public function individual_company_can_save_ipn(): void
    {
        $this->company->update([
            'business_type' => BusinessType::Individual,
            'ipn'           => '1234567890',
            'edrpou'        => null,
        ]);

        $this->assertDatabaseHas('companies', [
            'id'            => $this->company->id,
            'business_type' => 'individual',
            'ipn'           => '1234567890',
            'edrpou'        => null,
        ]);
    }

    #[Test]
    public function edrpou_must_be_exactly_8_digits(): void
    {
        $this->actingAs($this->employer);

        // Знайди маршрут оновлення профілю компанії у своєму проекті
        $response = $this->patch(route('employer.company.update'), [
            'business_type' => 'legal',
            'edrpou'        => '123',  // замало
        ]);

        $response->assertSessionHasErrors('edrpou');
    }

    #[Test]
    public function ipn_must_be_exactly_10_digits(): void
    {
        $this->actingAs($this->employer);

        $response = $this->patch(route('employer.company.update'), [
            'business_type' => 'individual',
            'ipn'           => '12345',  // замало
        ]);

        $response->assertSessionHasErrors('ipn');
    }

    #[Test]
    public function edrpou_not_required_for_individual(): void
    {
        $this->actingAs($this->employer);

        $response = $this->patch(route('employer.company.update'), [
            'business_type' => 'individual',
            'ipn'           => '1234567890',
            // edrpou відсутній — не повинно бути помилки
        ]);

        $response->assertSessionMissing('errors');
    }

    #[Test]
    public function ipn_not_required_for_legal(): void
    {
        $this->actingAs($this->employer);

        $response = $this->patch(route('employer.company.update'), [
            'business_type' => 'legal',
            'edrpou'        => '12345678',
            // ipn відсутній — не повинно бути помилки
        ]);

        $response->assertSessionMissing('errors');
    }

    #[Test]
    public function tax_id_accessor_returns_correct_value_for_legal(): void
    {
        $this->company->update([
            'business_type' => BusinessType::Legal,
            'edrpou'        => '87654321',
        ]);

        $this->assertEquals('87654321', $this->company->fresh()->tax_id);
    }

    #[Test]
    public function tax_id_accessor_returns_correct_value_for_individual(): void
    {
        $this->company->update([
            'business_type' => BusinessType::Individual,
            'ipn'           => '9876543210',
        ]);

        $this->assertEquals('9876543210', $this->company->fresh()->tax_id);
    }
}
```

Запусти тести:

```bash
php artisan test tests/Feature/Employer/CompanyTaxIdTest.php --stop-on-failure
```

---

## Чеклист виконання

- [ ] Міграція створена і виконана (`php artisan migrate`)
- [ ] Enum `BusinessType` створено в `app/Enums/`
- [ ] Модель `Company` оновлена (`fillable`, `casts`, accessors)
- [ ] Form Request з валідацією для 8/10 цифр
- [ ] Volt-компонент: реактивне перемикання полів через `wire:model.live`
- [ ] Filament ресурс: `->live()` + `->visible()` на полях
- [ ] 7/7 тестів зелені
- [ ] При зміні типу з `legal` на `individual` — ЄДРПОУ очищується (і навпаки)
