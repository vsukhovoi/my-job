# Завдання: Модальне вікно заповненості профілю роботодавця (мобільна версія)

## Контекст

Платформа My Job (myjob.co.ua). Стек: Laravel 13, Livewire/Volt, Tailwind CSS.

Вже реалізовано:
- `ProfileCompletenessService` — повертає відсоток заповненості та масив `missing_steps` для роботодавця
- Volt-компонент `shared.profile-completeness` — вбудований у дашборд (sidebar/widget)
- Модель `User` з роллю `UserRole::Employer`

## Що потрібно зробити

### 1. Міграція — таблиця для відстеження показів модалки

Створи міграцію `add_profile_modal_shown_at_to_users_table`:

```php
$table->timestamp('profile_completeness_modal_shown_at')->nullable();
```

### 2. Логіка показу модалки

Модалка повинна з'являтися **при кожній авторизації** роботодавця, або **раз на добу** — поки профіль не заповнено на 100%.

Умова показу (перевіряється у Volt-компоненті):
```php
$user = auth()->user();
$completeness = app(ProfileCompletenessService::class)->forEmployer($user);

$shouldShow = $completeness['percentage'] < 100
    && (
        is_null($user->profile_completeness_modal_shown_at)
        || $user->profile_completeness_modal_shown_at->lt(now()->startOfDay())
    );
```

Після показу — оновлювати `profile_completeness_modal_shown_at = now()`.

При кліку "Заповнити до 100%" — редірект на сторінку редагування профілю компанії (`route('employer.company.edit')`).

При кліку "Нагадати пізніше" — закрити модалку, оновити timestamp (щоб не показувалась до завтра).

### 3. Volt-компонент `employer.profile-completeness-modal`

Файл: `resources/views/livewire/employer/profile-completeness-modal.blade.php`

Це **нова** Volt-компонент-модалка, окрема від існуючого `shared.profile-completeness` widget.

```php
use function Livewire\Volt\{state, mount, action};
use App\Services\ProfileCompletenessService;
use App\Enums\UserRole;

state(['show' => false, 'percentage' => 0, 'nextStep' => null]);

mount(function () {
    $user = auth()->user();
    if (!$user || $user->role !== UserRole::Employer) return;

    $data = app(ProfileCompletenessService::class)->forEmployer($user);
    $this->percentage = $data['percentage'];
    $this->nextStep = $data['next_step'] ?? null;

    $shouldShow = $this->percentage < 100
        && (
            is_null($user->profile_completeness_modal_shown_at)
            || $user->profile_completeness_modal_shown_at->lt(now()->startOfDay())
        );

    if ($shouldShow) {
        $user->update(['profile_completeness_modal_shown_at' => now()]);
        $this->show = true;
    }
});

$dismiss = action(function () {
    $this->show = false;
});

$goFill = action(function () {
    $this->show = false;
    $this->redirect(route('employer.company.edit'));
});
```

### 4. UI модалки — мобільна версія

Модалка — **bottom sheet** на мобільних (slide up знизу). На desktop — звичайний centered modal.

**Структура блоку:**

```
┌─────────────────────────────────────┐
│  Ваш профіль заповнено на {%}% 🎯  │
│  ██████████░░░░░░  {%}%             │  ← прогрес-бар
│                                     │
│  🚀 Більше довіри                   │
│     Профілі зі 100% заповненням     │
│     отримують на 40% більше         │
│     відгуків від кандидатів.        │
│                                     │
│  🔝 Вище у пошуку                   │
│     Повністю готові профілі         │
│     відображаються першими у        │
│     списках роботодавців.           │
│                                     │
│  ⏱ Економія часу                    │
│     Шукачі одразу бачать ваші       │
│     переваги та умови, що відсіює   │
│     нерелевантних кандидатів.       │
│                                     │
│  [   Заповнити до 100%   ] ← CTA    │
│       Нагадати пізніше              │  ← текст-посилання
└─────────────────────────────────────┘
```

**Tailwind-класи для bottom sheet (mobile-first):**
- Overlay: `fixed inset-0 bg-black/50 z-50`
- Sheet: `fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl p-6 z-50 md:relative md:rounded-xl md:max-w-md md:mx-auto md:top-1/2 md:-translate-y-1/2`
- Анімація: `x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"`

**Прогрес-бар:**
```html
<div class="w-full bg-gray-100 rounded-full h-2.5 mb-4">
    <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
         style="width: {{ $percentage }}%"></div>
</div>
```

**Заголовок:** `Ваш профіль заповнено на {{ $percentage }}%`

**Три переваги** (іконка + заголовок + текст):
1. 🚀 **Більше довіри** — Профілі зі 100% заповненням отримують на 40% більше відгуків від кандидатів.
2. 🔝 **Вище у пошуку** — Повністю готові профілі відображаються першими у списках роботодавців.
3. ⏱ **Економія часу** — Шукачі одразу бачать ваші переваги та умови, що відсіює нерелевантних кандидатів.

**CTA кнопка:** `Заповнити до 100%` — синя, повна ширина, `wire:click="goFill"`

**Посилання нижче:** `Нагадати пізніше` — сірий текст по центру, `wire:click="dismiss"`

### 5. Підключення компонента

У layout роботодавця (`resources/views/layouts/employer.blade.php` або відповідний файл), після `@auth` — додати:

```blade
@auth
    @if(auth()->user()->role === \App\Enums\UserRole::Employer)
        <livewire:employer.profile-completeness-modal />
    @endif
@endauth
```

### 6. PHPUnit тести

Файл: `tests/Feature/Employer/ProfileCompletenessModalTest.php`

Тести (PHPUnit 12, `#[Test]` атрибути):

1. `modal_shows_on_first_login_when_profile_incomplete` — роботодавець без `profile_completeness_modal_shown_at`, профіль < 100% → `show = true`
2. `modal_does_not_show_when_profile_complete` — профіль 100% → `show = false`
3. `modal_does_not_show_twice_same_day` — `profile_completeness_modal_shown_at = now()` → `show = false`
4. `modal_shows_again_next_day` — `profile_completeness_modal_shown_at = yesterday` → `show = true`
5. `modal_not_shown_to_candidate` — роль `UserRole::Candidate` → `show = false`
6. `dismiss_action_closes_modal` — `dismiss()` → `show = false`
7. `go_fill_action_redirects_to_company_edit` — `goFill()` → redirect на `employer.company.edit`

**Важливо:** `actingAs($user)` викликати **до** `Volt::test()`, не в ланцюжку.

## Що НЕ потрібно чіпати

- Існуючий `shared.profile-completeness` widget — залишити як є
- `ProfileCompletenessService` — не модифікувати
- Будь-які інші тести — не чіпати

## Порядок виконання

1. Міграція
2. Додати `profile_completeness_modal_shown_at` до `$fillable` у `User`
3. Volt-компонент
4. Підключити у layout
5. PHPUnit тести
6. Запустити тести: `php artisan test tests/Feature/Employer/ProfileCompletenessModalTest.php`
7. Повідомити про результати
