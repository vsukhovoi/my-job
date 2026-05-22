# МОДУЛЬ 7 (розгорнутий). Livewire/Volt — лічильник «Залишилось N днів» у реальному часі

## 🎯 Мета модуля
Створити reusable Livewire-компонент, який показує роботодавцю стан його вакансії: статус, залишок часу до завершення, і CTA-кнопку «Продовжити публікацію». Має оновлюватися сам без перезавантаження сторінки, з правильною українською плюралізацією, з адаптивним дизайном на Tailwind.

**Передумова:** модулі 1–4 виконано (модель `Vacancy` має `countdown_label`, `is_active`, scopes).

---

## 🤔 КРОК 7.1. Питання до мене перед стартом

Виведи мені поточний стан і запитай:

```bash
# 1. Який Livewire?
composer show livewire/livewire | grep versions
composer show livewire/volt | grep versions 2>/dev/null

# 2. Чи в проєкті використовується Volt (single-file components) чи класичний Livewire?
ls resources/views/livewire/ 2>/dev/null | head -5
ls app/Livewire/ 2>/dev/null | head -5

# 3. Сторінка перегляду вакансії роботодавцем — це окремий Livewire-компонент чи Blade-вʼюшка?
find resources/views -name "*vacanc*" -type f
find app/Livewire -name "*Vacanc*" -type f 2>/dev/null
```

**Запитай мене:**
> Я бачу `<що знайдено>`. Куди вбудувати компонент countdown — у вже наявну сторінку перегляду чи це новий екран? Який стиль реалізації — Volt single-file чи класичний клас + Blade?

---

## 🎨 КРОК 7.2. Дизайн-специфікація

```
┌──────────────────────────────────────────────────┐
│  🟢 Активна публікація                           │  ← статус-бейдж (color із enum)
│                                                  │
│  Залишилось 3 дні 14 годин                       │  ← основний лічильник
│  до 25 листопада 2025, 18:30                     │  ← повна дата (підказка)
│                                                  │
│  ████████░░░░░░░░░░░░░░░░░░░░░  10%              │  ← прогрес-бар (за бажанням)
│                                                  │
│  [ Продовжити публікацію ]                       │  ← primary CTA
│  [ Архівувати ]                                  │  ← secondary
└──────────────────────────────────────────────────┘
```

**Стани компонента:**

| `vacancy.status` | Бейдж | Лічильник | Кнопки |
|------------------|-------|-----------|--------|
| `Draft` | сірий «Чернетка» | «Не опубліковано» | `[Опублікувати]` |
| `Active`, > 24h | зелений «Активна» | «Залишилось N днів» | `[Продовжити] [Архівувати]` |
| `Active`, < 24h | жовтий «Завершується» | «Залишилось 14 годин» | `[Продовжити (зробити primary, акцент)] [Архівувати]` |
| `Active`, < 1h | червоний «Завершується» | «Залишилось 32 хвилини» | `[Продовжити]` (з пульсацією) |
| `Expired` | червоний «Завершено» | «Публікацію завершено N днів тому» | `[Поновити публікацію]` |
| `Archived` | сірий «В архіві» | «В архіві» | `[Відновити]` (якщо дозволено) |

---

## 📂 КРОК 7.3. Реалізація — варіант Volt (single-file)

Якщо проєкт на Volt, файл `resources/views/livewire/vacancy-countdown.blade.php`:

```php
<?php

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Livewire\Volt\Component;

new class extends Component {
    public Vacancy $vacancy;

    /**
     * Період опитування у секундах.
     * 60 — оптимально: достатньо часто, щоб лічильник не «застрягав»,
     * але не настільки часто, щоб створити навантаження на сервер.
     */
    public int $pollInterval = 60;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy = $vacancy;
    }

    /**
     * Поновлення даних — викликається wire:poll.
     * Можна навіть не оголошувати: wire:poll сам ререндерить компонент.
     * Але явний refresh() корисний для тестів і для майбутніх дій.
     */
    public function refresh(): void
    {
        $this->vacancy->refresh();
    }

    /**
     * Чи треба показувати «терміновий» режим (червоний акцент, пульсація)?
     */
    public function getIsCriticalProperty(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 24;
    }

    /**
     * Чи треба показувати «попередній» режим (жовтий)?
     */
    public function getIsWarningProperty(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 72
            && ! $this->is_critical;
    }

    /**
     * Прогрес-бар: відсоток ВИКОРИСТАНОГО часу публікації.
     * 0% — щойно опублікували, 100% — час вийшов.
     */
    public function getProgressPercentProperty(): int
    {
        if (! $this->vacancy->is_active || ! $this->vacancy->expires_at || ! $this->vacancy->published_at) {
            return 0;
        }

        $totalSeconds = $this->vacancy->published_at->diffInSeconds($this->vacancy->expires_at, absolute: true);
        $elapsedSeconds = $this->vacancy->published_at->diffInSeconds(now(), absolute: true);

        if ($totalSeconds === 0) {
            return 100;
        }

        return min(100, max(0, (int) round($elapsedSeconds / $totalSeconds * 100)));
    }

    /**
     * Опис для expired: «Завершилась 5 днів тому».
     */
    public function getExpiredAgoLabelProperty(): ?string
    {
        if ($this->vacancy->status !== VacancyStatus::Expired || ! $this->vacancy->expires_at) {
            return null;
        }

        // Carbon має вбудовану українську локалізацію
        return 'Завершено ' . $this->vacancy->expires_at->locale('uk')->diffForHumans();
    }
}; ?>

<div
    wire:poll.{{ $pollInterval }}s="refresh"
    class="rounded-lg border bg-white p-6 shadow-sm @if($this->is_critical) border-red-300 @elseif($this->is_warning) border-yellow-300 @else border-gray-200 @endif"
    aria-live="polite"
>
    {{-- Бейдж статусу --}}
    <div class="flex items-center gap-2 mb-4">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $vacancy->status->badgeClass() }}">
            <span class="w-1.5 h-1.5 rounded-full
                @if($vacancy->status === VacancyStatus::Active) bg-green-500 @if($this->is_critical)animate-pulse @endif
                @elseif($vacancy->status === VacancyStatus::Expired) bg-yellow-500
                @elseif($vacancy->status === VacancyStatus::Archived) bg-red-500
                @else bg-gray-500
                @endif
            "></span>
            {{ $vacancy->status->label() }}
        </span>

        @if($this->is_critical)
            <span class="text-xs text-red-600 font-medium">⚠ Терміново</span>
        @endif
    </div>

    {{-- Основний лічильник --}}
    <div class="space-y-1">
        <p class="text-2xl font-semibold @if($this->is_critical) text-red-700 @elseif($this->is_warning) text-yellow-700 @else text-gray-900 @endif">
            {{ $vacancy->countdown_label }}
        </p>

        @if($vacancy->is_active && $vacancy->expires_at)
            <p class="text-sm text-gray-500">
                до {{ $vacancy->expires_at->locale('uk')->isoFormat('D MMMM YYYY, HH:mm') }}
            </p>
        @elseif($expiredAgoLabel = $this->expired_ago_label)
            <p class="text-sm text-gray-500">{{ $expiredAgoLabel }}</p>
        @endif
    </div>

    {{-- Прогрес-бар (тільки для активних) --}}
    @if($vacancy->is_active)
        <div class="mt-4">
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full transition-all duration-1000 ease-out
                        @if($this->is_critical) bg-red-500 @elseif($this->is_warning) bg-yellow-500 @else bg-green-500 @endif"
                    style="width: {{ $this->progress_percent }}%"
                    role="progressbar"
                    aria-valuenow="{{ $this->progress_percent }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                ></div>
            </div>
            <p class="mt-1 text-xs text-gray-400 text-right">{{ $this->progress_percent }}% часу публікації минуло</p>
        </div>
    @endif

    {{-- Кнопки дій --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @if($vacancy->status === VacancyStatus::Active || $vacancy->status === VacancyStatus::Expired)
            <a
                href="{{ route('vacancies.extend', $vacancy) }}"
                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium
                    @if($this->is_critical) bg-red-600 hover:bg-red-700 text-white
                    @else bg-blue-600 hover:bg-blue-700 text-white
                    @endif"
            >
                @if($vacancy->status === VacancyStatus::Expired)
                    Поновити публікацію
                @else
                    Продовжити публікацію
                @endif
            </a>

            @if($vacancy->status === VacancyStatus::Active)
                <button
                    wire:click="$dispatch('open-archive-modal', { id: {{ $vacancy->id }} })"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium border border-gray-300 hover:bg-gray-50 text-gray-700"
                >
                    Архівувати
                </button>
            @endif
        @elseif($vacancy->status === VacancyStatus::Draft)
            <a
                href="{{ route('vacancies.publish', $vacancy) }}"
                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white"
            >
                Опублікувати
            </a>
        @endif
    </div>
</div>
```

---

## 📂 КРОК 7.4. Альтернатива — класичний Livewire

Якщо проєкт НЕ на Volt:

```bash
php artisan make:livewire VacancyCountdown
```

`app/Livewire/VacancyCountdown.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Livewire\Attributes\Computed;
use Livewire\Component;

class VacancyCountdown extends Component
{
    public Vacancy $vacancy;
    public int $pollInterval = 60;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy = $vacancy;
    }

    public function refresh(): void
    {
        $this->vacancy->refresh();
    }

    #[Computed]
    public function isCritical(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 24;
    }

    #[Computed]
    public function isWarning(): bool
    {
        return $this->vacancy->is_active
            && $this->vacancy->hours_left !== null
            && $this->vacancy->hours_left < 72
            && ! $this->isCritical;
    }

    #[Computed]
    public function progressPercent(): int
    {
        if (! $this->vacancy->is_active || ! $this->vacancy->expires_at || ! $this->vacancy->published_at) {
            return 0;
        }

        $total = $this->vacancy->published_at->diffInSeconds($this->vacancy->expires_at, absolute: true);
        $elapsed = $this->vacancy->published_at->diffInSeconds(now(), absolute: true);

        return $total === 0 ? 100 : min(100, max(0, (int) round($elapsed / $total * 100)));
    }

    public function render()
    {
        return view('livewire.vacancy-countdown');
    }
}
```

Шаблон `resources/views/livewire/vacancy-countdown.blade.php` — той самий, що в Volt-варіанті, але БЕЗ `<?php new class extends Component {...} ?>` блоку.

---

## 🔌 КРОК 7.5. Вбудовування в сторінку

У сторінці перегляду вакансії роботодавцем (наприклад, `resources/views/employer/vacancies/show.blade.php`):

```blade
<x-employer.layout>
    {{-- Інші блоки сторінки --}}

    {{-- Лічильник у sidebar --}}
    <aside class="sticky top-4">
        <livewire:vacancy-countdown :vacancy="$vacancy" :wire:key="'countdown-'.$vacancy->id" />
    </aside>
</x-employer.layout>
```

**Важливо:** `wire:key` обов'язковий, якщо на сторінці може бути більше одного компонента (наприклад, у списку вакансій).

---

## 🎭 КРОК 7.6. Локалізація Carbon

Файл `config/app.php`:

```php
'locale' => 'uk',
'timezone' => 'Europe/Kyiv',
```

Файл `bootstrap/app.php` (Laravel 11+) або `AppServiceProvider::boot()`:

```php
\Carbon\Carbon::setLocale('uk');
```

Без цього `diffForHumans()` поверне англійською («2 days ago» замість «2 дні тому»).

**Перевірка:**
```php
php artisan tinker --execute="echo now()->subDays(2)->diffForHumans();"
// Очікуване: "2 дні тому"
```

---

## ⚠️ Критичні нюанси

### 1. Чому `wire:poll.60s`, а не `setInterval` JS
- `wire:poll` — тривіально тестується через `Livewire::test()->call('refresh')`.
- Стейт переходить через сервер, тобто завжди консистентний з БД (після scheduler-а).
- Можна вимкнути для прихованої вкладки автоматично (Livewire 3+ це робить).

### 2. Чому 60 секунд, а не кожну секунду
- При секундному поллінгу 1000 одночасних користувачів = 1000 запитів/сек на бекенд лише для лічильника. Reading-heavy.
- Користувач на пів-хвилини точність не помітить — для дня/години 60s достатньо.
- Якщо потрібен SECOND-level countdown — зроби це **на JS**, без Livewire (Alpine.js + computed з `expires_at`), без серверного rountrip.

### 3. AlpineJS-альтернатива для секундного відліку (опційно)
Якщо все ж потрібно «3 дні 14 годин 23 хвилини 47 секунд» — додай поверх Livewire-блоку:

```blade
<div
    x-data="{
        expiresAt: new Date('{{ $vacancy->expires_at?->toIso8601String() }}').getTime(),
        now: Date.now(),
        get secondsLeft() { return Math.max(0, Math.floor((this.expiresAt - this.now) / 1000)); },
        get formatted() {
            const s = this.secondsLeft;
            const d = Math.floor(s / 86400);
            const h = Math.floor((s % 86400) / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return `${d}д ${h}г ${m}х ${sec}с`;
        }
    }"
    x-init="setInterval(() => now = Date.now(), 1000)"
    x-text="formatted"
    class="font-mono text-sm text-gray-500"
></div>
```

**АЛЕ:** клієнтський час може бути неправильним (зсунутий годинник). Якщо це важливо — використовуй `wire:poll` як sync-point.

### 4. Дублікат логіки плюралізації — вже в моделі
Не пиши плюралізацію в Blade. У моделі є `countdown_label` — там уже все правильно. Якщо в Blade хочеться кастомний формат — додай ще один accessor у моделі (наприклад, `short_countdown_label`), а не дублюй логіку.

### 5. `aria-live="polite"` для скрін-рідерів
Незрячі користувачі повинні почути зміну стану, але не бути перебитими (як з `assertive`). `polite` каже «оголоси при найближчій паузі».

### 6. Чому поллінг `wire:poll`, а НЕ Laravel Echo / Reverb
- Echo/Reverb потребує WebSocket-сервера (Reverb або Pusher). Це інфраструктурна залежність.
- Echo має сенс, коли ОНОВЛЕННЯ — НЕЧАСТЕ, але ВАЖЛИВЕ (новий applicant). Лічильник — інше: він поступово «зменшується», і тут pulling простіший.
- Якщо в проєкті Reverb уже налаштовано — обговори зі мною, чи варто переходити на broadcasting.

### 7. SSR / first paint
При першому завантаженні сторінки `countdown_label` обчислюється на сервері (через accessor моделі). Тобто користувач БАЧИТЬ значення одразу, ще до того, як Livewire/Alpine ініціалізується. Це CLS-friendly.

### 8. Не використовуй `wire:poll.keep-alive`
`keep-alive` тримає сесію живою, навіть коли вкладка прихована. Це антифіча для нашого кейсу — користувач переключився на іншу вкладку, нам не потрібно дзюрити запити для нього.

---

## 🧪 КРОК 7.7. Перевірка вручну

```bash
# 1. Створити тестову вакансію
php artisan tinker
```
```php
$v = Vacancy::factory()->create([
    'status' => 'active',
    'published_at' => now()->subDays(28),
    'expires_at' => now()->addDays(2),
]);
echo $v->id;
```

```bash
# 2. Відкрий сторінку перегляду
# http://localhost:8000/employer/vacancies/{id}

# 3. Перевір у DevTools:
#    - Network tab: запит wire:poll кожні 60 секунд
#    - HTML містить wire:poll.60s атрибут
#    - aria-live="polite"

# 4. Скоротити термін через tinker, перезавантажити вручну:
$v->update(['expires_at' => now()->addHours(2)]);
# Очікуване: бейдж стає жовтим, лейбл "Залишилось 2 години"

# 5. Зробити expired:
$v->update(['expires_at' => now()->subMinute(), 'status' => 'expired']);
# Очікуване: червоний бейдж, "Публікацію завершено хвилину тому", кнопка "Поновити"
```

---

## ✅ Очікуваний результат модуля

1. Компонент `VacancyCountdown` створено (Volt або класичний — за вибором).
2. Шаблон з усіма станами (Draft, Active, Expired, Archived).
3. Прогрес-бар з трьома кольорами (зелений / жовтий / червоний).
4. Українська локалізація Carbon працює.
5. Інтегровано в сторінку перегляду вакансії.
6. Ручний тест пройдено.
7. Звіт мені:
   ```
   Livewire-компонент VacancyCountdown готовий.
   wire:poll.60s, прогрес-бар, три рівні попередження.
   Інтегровано в employer/vacancies/show.

   Перейти до модуля 8 (Nutgram)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не додавай інлайн-стилі — тільки Tailwind utility-класи.
- ❌ Не пиши `setInterval` для серверних оновлень — використовуй `wire:poll`.
- ❌ Не використовуй `wire:poll.1s` — це DDoS на власний сервер.
- ❌ Не дублюй плюралізацію в Blade — використовуй accessors моделі.
- ❌ Не додавай у компонент логіку оплати чи архівації — лише посилання/dispatch на інші компоненти.
- ❌ Не зберігай `expires_at` у JS-таймстемпі без врахування TZ — використовуй `toIso8601String()`.
