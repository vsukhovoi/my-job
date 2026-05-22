# Claude Code Prompt: Employer Sidebar — сторінка вакансії

## Контекст

Файл: `resources/views/livewire/pages/jobs/show.blade.php`
Це Volt компонент (один файл: PHP-секція зверху + Blade-шаблон).
Sidebar знаходиться в `<aside class="mj-sidebar">` на рядку ~392.
CSS для sidebar — рядки ~979+, у тому самому файлі.

## Задача

Замінити вміст `<aside class="mj-sidebar">` для роботодавця-власника вакансії.
Зараз там відображається сірий текст «Роботодавці не можуть подавати заявки» — це потрібно повністю замінити на функціональний блок з трьома станами.

---

## Крок 1 — PHP-секція: додати обчислення стану

Знайди PHP-секцію Volt компонента (блок між `<?php` та `?>` або `use(...)` на початку файлу).
Додай наступну логіку **після** того, як змінна `$vacancy` вже доступна:

```php
// Employer sidebar state
$isOwner = auth()->check() && auth()->id() === $vacancy->user_id;

$sidebarState = null; // 'active' | 'expiring' | 'expired'
$daysLeft = null;
$expiresAt = null;

if ($isOwner && $vacancy->expires_at) {
    $expiresAt = $vacancy->expires_at;
    $daysLeft = (int) now()->diffInDays($expiresAt, false); // negative if expired

    if ($vacancy->status === 'expired' || $daysLeft < 0) {
        $sidebarState = 'expired';
    } elseif ($daysLeft <= 7) {
        $sidebarState = 'expiring';
    } else {
        $sidebarState = 'active';
    }
} elseif ($isOwner && !$vacancy->expires_at) {
    // Вакансія без expires_at — вважаємо активною без терміну
    $sidebarState = 'active';
}
```

---

## Крок 2 — Blade: замінити вміст `<aside class="mj-sidebar">`

Знайди блок `<aside class="mj-sidebar">` (~рядок 392).
Всередині aside знайди умову яка перевіряє роль роботодавця (зараз там текст «Роботодавці не можуть подавати заявки»).
Заміни **лише вміст цієї умовної гілки** (не чіпай гілки для шукача та аноніма) на наступний код:

```blade
@if($isOwner)
    {{-- ===== EMPLOYER OWNER SIDEBAR ===== --}}

    {{-- Статистика --}}
    <div class="mj-employer-stats">
        <div class="mj-employer-stat-box">
            <span class="mj-employer-stat-num">{{ $vacancy->applications_count ?? $vacancy->applications()->count() }}</span>
            <span class="mj-employer-stat-label">відгуків</span>
        </div>
        <div class="mj-employer-stat-box">
            <span class="mj-employer-stat-num">{{ $vacancy->views_count ?? 0 }}</span>
            <span class="mj-employer-stat-label">переглядів</span>
        </div>
    </div>

    {{-- Термін активності --}}
    @if($vacancy->expires_at)
        <div class="mj-employer-expiry">
            <div class="mj-employer-expiry-header">
                <span>Термін активності</span>
                @if($sidebarState === 'expired')
                    <span class="mj-expiry-badge mj-expiry-badge--expired">Вийшов</span>
                @elseif($sidebarState === 'expiring')
                    <span class="mj-expiry-badge mj-expiry-badge--expiring">{{ $daysLeft }} {{ Str::plural($daysLeft, ['день', 'дні', 'днів']) }}</span>
                @else
                    <span class="mj-expiry-badge mj-expiry-badge--active">{{ $daysLeft }} {{ Str::plural($daysLeft, ['день', 'дні', 'днів']) }}</span>
                @endif
            </div>
            <div class="mj-days-bar">
                @php
                    // Визначаємо загальний термін (припускаємо 30 днів якщо немає created_at)
                    $totalDays = $vacancy->created_at
                        ? (int) $vacancy->created_at->diffInDays($vacancy->expires_at)
                        : 30;
                    $totalDays = max($totalDays, 1);
                    $progressPct = $sidebarState === 'expired'
                        ? 0
                        : min(100, max(0, round(($daysLeft / $totalDays) * 100)));
                @endphp
                <div class="mj-days-fill mj-days-fill--{{ $sidebarState }}"
                     style="width: {{ $progressPct }}%"></div>
            </div>
            <div class="mj-employer-expiry-date">до {{ $vacancy->expires_at->format('d.m.Y') }}</div>
        </div>
    @endif

    {{-- Алерт: спливає або expired --}}
    @if($sidebarState === 'expiring')
        <div class="mj-employer-alert mj-employer-alert--warning">
            ⚠ Вакансія зникне з пошуку через {{ $daysLeft }} {{ Str::plural($daysLeft, ['день', 'дні', 'днів']) }}
        </div>
    @elseif($sidebarState === 'expired')
        <div class="mj-employer-alert mj-employer-alert--danger">
            ✕ Вакансія прихована з пошуку з {{ $vacancy->expires_at->format('d.m.Y') }}. Оплатіть продовження щоб відновити.
        </div>
    @endif

    {{-- CTA: оплата (тільки для expiring або expired) --}}
    @if($sidebarState === 'expiring')
        <a href="{{ route('vacancies.payment', $vacancy) }}"
           class="mj-employer-cta mj-employer-cta--warning">
            💳 Продовжити вакансію
        </a>
        <div class="mj-employer-cta-hint">15 / 30 / 90 днів</div>
    @elseif($sidebarState === 'expired')
        <a href="{{ route('vacancies.payment', $vacancy) }}"
           class="mj-employer-cta mj-employer-cta--danger">
            💳 Відновити вакансію
        </a>
        <div class="mj-employer-cta-hint">15 / 30 / 90 днів</div>
    @endif

    <div class="mj-employer-divider"></div>

    {{-- Швидкі дії --}}
    <a href="{{ route('employer.vacancies.edit', $vacancy) }}"
       class="mj-employer-action">
        ✏ Редагувати вакансію
    </a>

    <a href="{{ route('dashboard.employer') }}?tab=candidates&vacancy={{ $vacancy->id }}"
       class="mj-employer-action mj-employer-action--blue">
        → Відгуки ({{ $vacancy->applications_count ?? $vacancy->applications()->count() }})
    </a>

    <a href="{{ $vacancy->publicUrl() ?? route('jobs.show', $vacancy) }}?preview=1"
       target="_blank"
       class="mj-employer-action mj-employer-action--ghost">
        👁 Вигляд для шукача
    </a>

@endif
```

> **Увага:** Якщо роут `vacancies.payment` або `employer.vacancies.edit` називається інакше у твоєму проєкті — уточни і заміни назви роутів на правильні. Не вигадуй назви роутів — перевір через `php artisan route:list | grep vacanc`.

---

## Крок 3 — CSS: додати стилі в кінець файлу (рядки ~979+)

Знайди секцію CSS sidebar у файлі. Додай нові класи **після** існуючих стилів `.mj-sidebar`:

```css
/* ===== EMPLOYER OWNER SIDEBAR ===== */

.mj-employer-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 12px;
}

.mj-employer-stat-box {
    background: var(--mj-bg-secondary, rgba(255,255,255,0.05));
    border-radius: 8px;
    padding: 10px;
    text-align: center;
}

.mj-employer-stat-num {
    display: block;
    font-size: 22px;
    font-weight: 600;
    color: var(--mj-text-primary, #fff);
}

.mj-employer-stat-label {
    display: block;
    font-size: 11px;
    color: var(--mj-text-muted, rgba(255,255,255,0.5));
    margin-top: 2px;
}

/* Термін */
.mj-employer-expiry {
    margin-bottom: 10px;
}

.mj-employer-expiry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--mj-text-secondary, rgba(255,255,255,0.7));
    margin-bottom: 5px;
}

.mj-expiry-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
}

.mj-expiry-badge--active  { background: #2a4a14; color: #a3d977; }
.mj-expiry-badge--expiring { background: #4a3010; color: #FAC775; }
.mj-expiry-badge--expired  { background: #4a1414; color: #F09595; }

.mj-days-bar {
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 4px;
}

.mj-days-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.mj-days-fill--active   { background: #639922; }
.mj-days-fill--expiring { background: #BA7517; }
.mj-days-fill--expired  { background: #A32D2D; width: 0 !important; }

.mj-employer-expiry-date {
    font-size: 11px;
    color: var(--mj-text-muted, rgba(255,255,255,0.4));
}

/* Алерти */
.mj-employer-alert {
    border-radius: 8px;
    padding: 9px 11px;
    font-size: 12px;
    line-height: 1.4;
    margin-bottom: 10px;
}

.mj-employer-alert--warning {
    background: rgba(186, 117, 23, 0.15);
    border: 0.5px solid rgba(186, 117, 23, 0.4);
    color: #FAC775;
}

.mj-employer-alert--danger {
    background: rgba(163, 45, 45, 0.15);
    border: 0.5px solid rgba(163, 45, 45, 0.4);
    color: #F09595;
}

/* CTA кнопки оплати */
.mj-employer-cta {
    display: block;
    text-align: center;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 5px;
    transition: opacity 0.2s;
}

.mj-employer-cta:hover { opacity: 0.85; }

.mj-employer-cta--warning {
    background: #BA7517;
    color: #fff;
}

.mj-employer-cta--danger {
    background: #A32D2D;
    color: #fff;
}

.mj-employer-cta-hint {
    font-size: 11px;
    color: var(--mj-text-muted, rgba(255,255,255,0.4));
    text-align: center;
    margin-bottom: 10px;
}

/* Роздільник */
.mj-employer-divider {
    border: none;
    border-top: 0.5px solid rgba(255,255,255,0.08);
    margin: 10px 0;
}

/* Дії (посилання) */
.mj-employer-action {
    display: block;
    text-align: center;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    margin-bottom: 6px;
    border: 0.5px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: var(--mj-text-secondary, rgba(255,255,255,0.7));
    transition: background 0.2s;
}

.mj-employer-action:hover {
    background: rgba(255,255,255,0.08);
}

.mj-employer-action--blue {
    background: rgba(24, 95, 165, 0.2);
    border-color: rgba(24, 95, 165, 0.4);
    color: #85B7EB;
}

.mj-employer-action--ghost {
    color: var(--mj-text-muted, rgba(255,255,255,0.45));
    font-size: 12px;
}
```

---

## Крок 4 — Перевірка роутів перед запуском

Перед тим як писати будь-який код, виконай:

```bash
php artisan route:list | grep -E "vacanc|employer|payment"
```

І заміни у Blade-коді назви роутів на реальні з виводу команди:
- `vacancies.payment` → реальна назва роуту оплати/продовження
- `employer.vacancies.edit` → реальна назва роуту редагування вакансії
- `dashboard.employer` → реальна назва роуту кабінету роботодавця

---

## Крок 5 — Перевірка результату

1. Відкрий вакансію як власник (`Mr. First`) — має з'явитись новий sidebar
2. Тимчасово встанови `$vacancy->expires_at = now()->addDays(3)` щоб перевірити стан **expiring**
3. Тимчасово встанови `$vacancy->status = 'expired'` щоб перевірити стан **expired**
4. Відкрий вакансію як шукач (`Vlad MMM`) — sidebar шукача має залишитись незмінним
5. Відкрий без авторизації — має показуватись публічний sidebar без змін

---

## Що НЕ чіпати

- Логіку sidebar для шукача (кнопки «Відгукнутись», «Зберегти»)
- Логіку sidebar для аноніма (кнопка «Увійти та відгукнутись»)
- Загальну структуру `<aside class="mj-sidebar">` і її CSS
- PHP-логіку завантаження `$vacancy` та `$applications`
