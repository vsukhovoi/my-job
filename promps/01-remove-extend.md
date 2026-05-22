# My Job — Промпт 1: Видалення extend

## Контекст

Маршрут `dashboard/employer/vacancies/extend` та пов'язана функціональність
видаляються повністю. Єдина точка управління підпискою — `dashboard/employer/billing`.
Кнопка «Продовжити вакансію» замінюється на відкриття Modal #10 (`showProfileModal = true`).

Стек: Laravel 13, Livewire 3 Volt, Filament, PHPUnit 12.
Конвенції: Enum-и, `#[Test]` атрибути, `actingAs()` перед `Volt::test()`.

---

## Крок 1 — Видалити маршрут

Відкрий `routes/web.php` і видали маршрут(и) пов'язані з `extend`:

```php
// ВИДАЛИТИ — будь-який з варіантів:
Route::get('/dashboard/employer/vacancies/extend', ...);
Route::post('/dashboard/employer/vacancies/extend', ...);
Route::get('/dashboard/employer/vacancies/{vacancy}/extend', ...);
Route::post('/dashboard/employer/vacancies/{vacancy}/extend', ...);

// Також видали named route якщо є:
// 'employer.vacancies.extend'
```

---

## Крок 2 — Видалити Volt-компонент

Знайди і видали файл компонента extend. Можливі шляхи:

```bash
# Перевір наявність файлу:
find resources/views/livewire -name "*extend*"
find app/Livewire -name "*extend*"

# Видали знайдений файл:
rm resources/views/livewire/employer/vacancies/extend.blade.php
# або
rm resources/views/livewire/pages/employer/vacancy-extend.blade.php
```

---

## Крок 3 — Замінити кнопку «Продовжити вакансію»

Знайди файл розгорнутого перегляду вакансії роботодавця.
Можливі шляхи:
- `resources/views/livewire/pages/employer/vacancy-show.blade.php`
- `resources/views/livewire/employer/vacancy-detail.blade.php`

Знайди кнопку «Продовжити вакансію» і заміни:

```blade
{{-- ВИДАЛИТИ — посилання на extend --}}
<a href="{{ route('employer.vacancies.extend', $vacancy) }}">
    Продовжити вакансію
</a>

{{-- або --}}
<button wire:click="extend">Продовжити вакансію</button>
```

Замінити на:

```blade
{{-- ДОДАТИ — відкриває Modal #10 --}}
<button
    wire:click="$set('showProfileModal', true)"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-sm transition"
    style="background: #F36F21; color: #FFFFFF;">
    <x-heroicon-o-arrow-path class="w-4 h-4" />
    Продовжити вакансію
</button>
```

> **Важливо:** якщо vacancy-show є окремим Volt-компонентом від dashboard,
> переконайся що `showProfileModal` доступна через `Livewire.dispatch`:

```blade
{{-- Альтернатива якщо компоненти різні --}}
<button
    onclick="Livewire.dispatch('open-profile-modal')"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-sm transition"
    style="background: #F36F21; color: #FFFFFF;">
    <x-heroicon-o-arrow-path class="w-4 h-4" />
    Продовжити вакансію
</button>
```

І у dashboard Volt-компоненті додай listener:

```php
#[On('open-profile-modal')]
public function openProfileModal(): void
{
    $this->showProfileModal = true;
}
```

---

## Крок 4 — Прибрати всі посилання на extend

Знайди і виправ всі інші місця де згадується `extend`:

```bash
# Пошук по проекту:
grep -r "extend" resources/views/livewire/employer/ --include="*.php" --include="*.blade.php" -l
grep -r "vacancies.extend\|vacancy.extend\|extend" routes/ -l
```

Можливі місця:
- `resources/views/livewire/pages/employer/dashboard.blade.php` — таблиця вакансій
- `resources/views/components/employer-tabs.blade.php`
- Будь-які інші blade/Volt файли

Видали або закоментуй знайдені посилання.

---

## Крок 5 — Перевірка

```bash
# Переконайся що маршрут більше не існує:
php artisan route:list | grep extend

# Має повернути порожній результат.

# Запусти існуючі тести щоб переконатись у відсутності регресій:
php artisan test --stop-on-failure
```

---

## Чеклист

- [ ] Маршрут `extend` видалено з `routes/web.php`
- [ ] Volt-компонент `extend` видалено
- [ ] Кнопка «Продовжити вакансію» замінена на `showProfileModal = true`
- [ ] Всі посилання на `route('employer.vacancies.extend')` видалено
- [ ] `php artisan route:list | grep extend` — порожній результат
- [ ] Існуючі тести — без регресій
