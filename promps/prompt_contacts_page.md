# Промпт для Claude Code: Сторінка контактів із формою зворотного зв'язку

## Контекст проєкту

- Laravel 13, Livewire/Volt, Filament, PostgreSQL
- Лише Volt-компоненти (не стандартні Livewire)
- PHPUnit 12 з `#[Test]` атрибутами (не docblock)
- `actingAs()` викликати ДО `Volt::test()` — ніколи не чейнити
- Ролі через `UserRole` Enum, статуси через Enum-и
- Форма зворотного зв'язку НЕ вимагає авторизації (публічна сторінка)

---

## Завдання

Реалізувати сторінку `/contacts` із формою зворотного зв'язку:
- зберігати звернення в БД
- відображати в Filament (адмін-панель)

---

## КРОК 1. Міграція

Файл: `database/migrations/YYYY_MM_DD_create_contact_messages_table.php`

```php
Schema::create('contact_messages', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('contact');           // email або Telegram
    $table->string('role');              // seeker | employer | partnership | other
    $table->string('topic')->nullable();
    $table->text('message');
    $table->boolean('is_read')->default(false);
    $table->timestamps();
});
```

---

## КРОК 2. Enum для ролі відправника

Файл: `app/Enums/ContactRole.php`

```php
<?php

namespace App\Enums;

enum ContactRole: string
{
    case Seeker      = 'seeker';
    case Employer    = 'employer';
    case Partnership = 'partnership';
    case Other       = 'other';

    public function label(): string
    {
        return match($this) {
            self::Seeker      => 'Шукаю роботу',
            self::Employer    => 'Роботодавець',
            self::Partnership => 'Партнерство',
            self::Other       => 'Інше',
        };
    }

    public function recipientEmail(): string
    {
        return match($this) {
            self::Employer    => 'sales@myjob.co.ua',
            self::Partnership => 'partnership@myjob.co.ua',
            default           => 'support@myjob.co.ua',
        };
    }
}
```

---

## КРОК 3. Модель

Файл: `app/Models/ContactMessage.php`

```php
<?php

namespace App\Models;

use App\Enums\ContactRole;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'contact', 'role', 'topic', 'message', 'is_read',
    ];

    protected $casts = [
        'role'    => ContactRole::class,
        'is_read' => 'boolean',
    ];
}
```

---

## КРОК 4. Volt-компонент форми

Файл: `resources/views/livewire/contacts/contact-form.blade.php`

```php
<?php

use App\Enums\ContactRole;
use App\Models\ContactMessage;
use Livewire\Volt\Component;

new class extends Component {

    public string $name    = '';
    public string $contact = '';
    public string $role    = 'seeker';
    public string $topic   = '';
    public string $message = '';
    public bool   $sent    = false;

    public function topics(): array
    {
        return match($this->role) {
            'seeker'      => [
                'Перегляд або фільтрація оголошень',
                'Проблема з резюме або профілем',
                'Питання щодо відгуку на вакансію',
                'Технічна помилка',
                'Питання про підписку чи оплату',
                'Інше',
            ],
            'employer'    => [
                'Публікація або редагування вакансії',
                'Доступ до бази CV',
                'Питання про тарифи та оплату',
                'Анонімна публікація',
                'Технічна помилка',
                'Інше',
            ],
            'partnership' => [
                'Корпоративне рішення',
                'API-доступ',
                'Медіаспівпраця',
                'Реклама на платформі',
                'Інше',
            ],
            default       => ['Загальний відгук', 'Повідомити про помилку', 'Інше'],
        };
    }

    public function recipientEmail(): string
    {
        return ContactRole::from($this->role)->recipientEmail();
    }

    public function updatedRole(): void
    {
        $this->topic = '';
    }

    public function submit(): void
    {
        $this->validate([
            'name'    => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'role'    => ['required', 'in:seeker,employer,partnership,other'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::create([
            'name'    => $this->name,
            'contact' => $this->contact,
            'role'    => $this->role,
            'topic'   => $this->topic ?: null,
            'message' => $this->message,
        ]);

        $this->sent = true;
    }
};
?>

<div>
    @if($sent)
        {{-- Success state --}}
        <div class="text-center py-10">
            <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-4">
                <x-heroicon-o-check class="w-7 h-7 text-green-600" />
            </div>
            <h3 class="text-lg font-medium mb-1">Звернення надіслано!</h3>
            <p class="text-sm text-gray-500">Ми отримали ваше повідомлення і відповімо протягом 1 робочого дня.</p>
        </div>
    @else
        <form wire:submit="submit" novalidate>

            {{-- Role toggle --}}
            <div class="flex gap-2 mb-4">
                @foreach(\App\Enums\ContactRole::cases() as $case)
                    <button
                        type="button"
                        wire:click="$set('role', '{{ $case->value }}')"
                        @class([
                            'flex-1 py-2 px-3 text-sm rounded-lg border transition',
                            'bg-green-50 border-green-500 text-green-800 font-medium' => $role === $case->value,
                            'bg-gray-50 border-gray-200 text-gray-500'               => $role !== $case->value,
                        ])
                    >
                        {{ $case->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Recipient hint --}}
            <p class="text-xs text-gray-400 mb-4">
                Лист надійде на <strong>{{ $this->recipientEmail() }}</strong>
            </p>

            {{-- Name + Contact --}}
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ім'я</label>
                    <input wire:model="name" type="text" placeholder="Олексій"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Email або Telegram</label>
                    <input wire:model="contact" type="text" placeholder="@username або email"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300" />
                    @error('contact') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Topic --}}
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Тема звернення</label>
                <select wire:model="topic"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300">
                    <option value="">Оберіть тему...</option>
                    @foreach($this->topics() as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Message --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Повідомлення</label>
                <textarea wire:model="message" rows="5" placeholder="Опишіть ваше питання або ситуацію..."
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300 resize-y"></textarea>
                @error('message') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                Надіслати звернення
            </button>

            <p class="text-[11px] text-gray-400 text-center mt-3 leading-relaxed">
                Натискаючи «Надіслати», ви погоджуєтесь з обробкою персональних даних
                відповідно до Політики конфіденційності My&nbsp;Job.
            </p>
        </form>
    @endif
</div>
```

---

## КРОК 5. Реєстрація Volt-компонента

У `app/Providers/VoltServiceProvider.php` або там, де реєструються Volt-компоненти:

```php
Volt::mount([
    resource_path('views/livewire'),
]);
```

(Якщо вже є — нічого не додавати.)

---

## КРОК 6. Маршрут

У `routes/web.php`:

```php
Route::get('/contacts', function () {
    return view('pages.contacts');
})->name('contacts');
```

---

## КРОК 7. Blade-сторінка

Файл: `resources/views/pages/contacts.blade.php`

Сторінка використовує головний layout проєкту (`x-layouts.app` або аналог).
Ліва колонка — статична інформація, права — Volt-компонент форми.

```blade
<x-layouts.app>
    <div class="max-w-5xl mx-auto px-4 py-12">

        {{-- Header --}}
        <div class="grid grid-cols-2 gap-8 items-end mb-10">
            <div>
                <h1 class="text-4xl font-medium leading-tight tracking-tight mb-3">
                    Контакти<br><span class="text-green-600">та підтримка</span>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed">
                    Ми відповідаємо на всі звернення протягом 1 робочого дня.
                    Напишіть нам — допоможемо.
                </p>
            </div>
            <div class="flex flex-col items-end gap-2 text-sm text-gray-500">
                <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                    <x-heroicon-o-headset class="w-4 h-4 text-green-600" />
                    support@myjob.co.ua
                    <span class="text-xs text-gray-400">підтримка</span>
                </span>
                <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                    <x-heroicon-o-building-office class="w-4 h-4 text-green-600" />
                    sales@myjob.co.ua
                    <span class="text-xs text-gray-400">роботодавцям</span>
                </span>
                <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                    <x-heroicon-o-hand-raised class="w-4 h-4 text-green-600" />
                    partnership@myjob.co.ua
                    <span class="text-xs text-gray-400">партнерство</span>
                </span>
                <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                    <x-heroicon-o-clock class="w-4 h-4 text-green-600" />
                    Пн–Пт, 09:00–18:00
                </span>
            </div>
        </div>

        <hr class="border-gray-100 mb-10" />

        <div class="grid grid-cols-[1fr_1.4fr] gap-12 items-start">

            {{-- Sidebar --}}
            <div>
                <h2 class="text-xs font-medium uppercase tracking-widest text-gray-400 mb-6">
                    Із чим допомагаємо
                </h2>
                <div class="space-y-6 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Для кандидатів</p>
                        <p class="text-gray-700 leading-relaxed">
                            Перегляд та фільтрація оголошень, редагування резюме, налаштування акаунту —
                            <a href="mailto:support@myjob.co.ua" class="text-green-600">support@myjob.co.ua</a>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Для роботодавців</p>
                        <p class="text-gray-700 leading-relaxed">
                            Публікація вакансій, тарифи, доступ до бази CV —
                            <a href="mailto:sales@myjob.co.ua" class="text-green-600">sales@myjob.co.ua</a>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Технічні питання</p>
                        <p class="text-gray-700 leading-relaxed">
                            Помилки на платформі, інтеграція з Telegram, оплата —
                            <a href="mailto:support@myjob.co.ua" class="text-green-600">support@myjob.co.ua</a>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Партнерство</p>
                        <p class="text-gray-700 leading-relaxed">
                            Корпоративні рішення, API-доступ, медіаспівпраця —
                            <a href="mailto:partnership@myjob.co.ua" class="text-green-600">partnership@myjob.co.ua</a>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-6">
                    <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Безкоштовна підтримка</span>
                    <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Українська мова</span>
                    <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Конфіденційно</span>
                </div>
            </div>

            {{-- Form card --}}
            <div class="bg-white border border-gray-100 rounded-2xl p-7 shadow-sm">
                <h2 class="text-base font-medium mb-5">Написати нам</h2>
                <livewire:contacts.contact-form />
            </div>

        </div>
    </div>
</x-layouts.app>
```

---

## КРОК 8. Filament Resource

```bash
php artisan make:filament-resource ContactMessage --generate
```

Файл: `app/Filament/Resources/ContactMessageResource.php`

```php
<?php

namespace App\Filament\Resources;

use App\Enums\ContactRole;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Звернення';
    protected static ?string $navigationGroup = 'Контент';
    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ім\'я')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact')
                    ->label('Контакт')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (ContactRole $state) => $state->label())
                    ->color(fn (ContactRole $state) => match($state) {
                        ContactRole::Seeker      => 'info',
                        ContactRole::Employer    => 'warning',
                        ContactRole::Partnership => 'success',
                        ContactRole::Other       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('topic')
                    ->label('Тема')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Повідомлення')
                    ->limit(60),

                Tables\Columns\IconColumn::make('is_read')
                    ->label('Прочитано')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Роль')
                    ->options(
                        collect(ContactRole::cases())
                            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                            ->toArray()
                    ),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Прочитано'),
            ])
            ->actions([
                Tables\Actions\Action::make('markRead')
                    ->label('Позначити прочитаним')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ContactMessage $record) => ! $record->is_read)
                    ->action(fn (ContactMessage $record) => $record->update(['is_read' => true])),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view'  => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
```

Створи також `Pages/ListContactMessages.php` і `Pages/ViewContactMessage.php` — стандартні Filament pages.

---

## КРОК 9. Тести

Файл: `tests/Feature/Contacts/ContactFormTest.php`

```php
<?php

namespace Tests\Feature\Contacts;

use App\Enums\ContactRole;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contacts_page_loads_successfully(): void
    {
        $this->get(route('contacts'))
            ->assertOk()
            ->assertSeeLivewire('contacts.contact-form');
    }

    #[Test]
    public function guest_can_submit_contact_form(): void
    {
        Volt::test('contacts.contact-form')
            ->set('name', 'Олексій Коваль')
            ->set('contact', 'oleksiy@example.com')
            ->set('role', 'seeker')
            ->set('topic', 'Технічна помилка')
            ->set('message', 'Не можу увійти в акаунт вже другий день')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', [
            'name'    => 'Олексій Коваль',
            'contact' => 'oleksiy@example.com',
            'role'    => 'seeker',
        ]);
    }

    #[Test]
    public function employer_message_saved_with_correct_role(): void
    {
        Volt::test('contacts.contact-form')
            ->set('name', 'ТОВ Рога і Копита')
            ->set('contact', 'hr@company.ua')
            ->set('role', 'employer')
            ->set('message', 'Цікавить корпоративний тариф для команди з 10 осіб')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', [
            'role' => ContactRole::Employer->value,
        ]);
    }

    #[Test]
    public function partnership_role_saved_correctly(): void
    {
        Volt::test('contacts.contact-form')
            ->set('name', 'Медіа партнер')
            ->set('contact', '@media_partner')
            ->set('role', 'partnership')
            ->set('message', 'Хочемо розмістити статтю про наш сервіс на вашій платформі')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', [
            'role' => ContactRole::Partnership->value,
        ]);
    }

    #[Test]
    public function validation_fails_when_name_missing(): void
    {
        Volt::test('contacts.contact-form')
            ->set('contact', 'test@example.com')
            ->set('role', 'seeker')
            ->set('message', 'Тестове повідомлення для перевірки валідації форми')
            ->call('submit')
            ->assertHasErrors(['name' => 'required']);
    }

    #[Test]
    public function validation_fails_when_message_too_short(): void
    {
        Volt::test('contacts.contact-form')
            ->set('name', 'Тест')
            ->set('contact', 'test@example.com')
            ->set('role', 'seeker')
            ->set('message', 'Коротко')
            ->call('submit')
            ->assertHasErrors(['message' => 'min']);
    }

    #[Test]
    public function topics_change_when_role_changes(): void
    {
        $component = Volt::test('contacts.contact-form')
            ->set('role', 'seeker');

        $this->assertContains(
            'Перегляд або фільтрація оголошень',
            $component->instance()->topics()
        );

        $component->set('role', 'employer');

        $this->assertContains(
            'Публікація або редагування вакансії',
            $component->instance()->topics()
        );
    }

    #[Test]
    public function recipient_email_matches_role(): void
    {
        $component = Volt::test('contacts.contact-form');

        $component->set('role', 'seeker');
        $this->assertEquals('support@myjob.co.ua', $component->instance()->recipientEmail());

        $component->set('role', 'employer');
        $this->assertEquals('sales@myjob.co.ua', $component->instance()->recipientEmail());

        $component->set('role', 'partnership');
        $this->assertEquals('partnership@myjob.co.ua', $component->instance()->recipientEmail());

        $component->set('role', 'other');
        $this->assertEquals('support@myjob.co.ua', $component->instance()->recipientEmail());
    }
}
```

---

## Чого НЕ робити

- Не використовувати стандартні Livewire компоненти — тільки Volt
- Не використовувати docblock `/** @test */` — тільки `#[Test]` атрибути
- Не чейнити `actingAs()` з `Volt::test()` (форма публічна, тут не актуально)
- Не використовувати рядкові ролі напряму — тільки через `ContactRole` Enum
- Не додавати відправку email (не потрібно за умовою завдання)
- Не чіпати існуючі міграції

---

## Результат

- `/contacts` — публічна сторінка з формою (без авторизації)
- `contact_messages` — таблиця в БД
- Filament: `/admin/contact-messages` — таблиця зі зверненнями, фільтри, позначення прочитаним
- 8 PHPUnit тестів у `tests/Feature/Contacts/ContactFormTest.php`
