# МОДУЛЬ 9. SEO для expired-вакансій

## 🎯 Мета
Завершені вакансії залишаються доступні за прямим URL, але:
- `<meta name="robots" content="noindex, follow">` — не індексується новими краулерами
- Видимий банер «Вакансія неактивна» з CTA на схожі активні
- Schema.org `JobPosting` з валідним `validThrough`
- Архівовані повертають **404** (повністю прибрані з пошуку)

**Передумова:** модулі 1–4, 5 виконано.

---

## 🔍 Розвідка

```bash
# Поточний контролер вакансій
find app/Http/Controllers -name "*Vacanc*"
find app/Livewire -name "*Vacanc*" 2>/dev/null

# Поточна route
php artisan route:list | grep vacanc
```

---

## 📂 Контролер

`app/Http/Controllers/VacancyController.php` — метод `show`:

```php
use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use App\Services\Vacancies\SimilarVacanciesService;

public function show(string $slug, SimilarVacanciesService $similarSvc)
{
    $vacancy = Vacancy::where('slug', $slug)->firstOrFail();

    // Архівована — повне 404
    if ($vacancy->status === VacancyStatus::Archived) {
        abort(404);
    }

    // Чернетка — лише автору
    if ($vacancy->status === VacancyStatus::Draft && auth()->id() !== $vacancy->user_id) {
        abort(404);
    }

    $similar = $vacancy->status === VacancyStatus::Expired
        ? $similarSvc->findFor($vacancy, limit: 6)
        : collect();

    return view('vacancies.show', [
        'vacancy' => $vacancy,
        'similar' => $similar,
        'isExpired' => $vacancy->status === VacancyStatus::Expired,
    ]);
}
```

---

## 🧩 SimilarVacanciesService

`app/Services/Vacancies/SimilarVacanciesService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Vacancies;

use App\Models\Vacancy;
use Illuminate\Support\Collection;

class SimilarVacanciesService
{
    /**
     * Шукає схожі активні вакансії: ту саму категорію + місто, fallback — категорія.
     */
    public function findFor(Vacancy $vacancy, int $limit = 6): Collection
    {
        $query = Vacancy::active()
            ->where('id', '!=', $vacancy->id);

        // Фільтр: та сама категорія
        if ($vacancy->category_id) {
            $query->where('category_id', $vacancy->category_id);
        }

        // Бонус: те саме місто
        if ($vacancy->city_id) {
            $query->orderByRaw('CASE WHEN city_id = ? THEN 0 ELSE 1 END', [$vacancy->city_id]);
        }

        return $query
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
```

---

## 🎨 Blade-шаблон

`resources/views/vacancies/show.blade.php`:

```blade
<x-app-layout>
    @push('head')
        @if($isExpired)
            {{-- noindex, follow — не індексувати, але переходити по лінкам --}}
            <meta name="robots" content="noindex, follow">
        @endif

        {{-- Schema.org JobPosting --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'JobPosting',
                'title' => $vacancy->title,
                'description' => strip_tags($vacancy->description),
                'datePosted' => $vacancy->published_at?->toIso8601String(),
                'validThrough' => $vacancy->expires_at?->toIso8601String(),
                'employmentType' => $vacancy->employment_type ?? 'FULL_TIME',
                'hiringOrganization' => [
                    '@type' => 'Organization',
                    'name' => $vacancy->employer->name ?? 'Роботодавець',
                ],
                'jobLocation' => $vacancy->city ? [
                    '@type' => 'Place',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $vacancy->city->name,
                        'addressCountry' => 'UA',
                    ],
                ] : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush

    @if($isExpired)
        <x-vacancy.expired-banner :vacancy="$vacancy" />
    @endif

    <article class="prose @if($isExpired) opacity-75 @endif">
        <h1>{{ $vacancy->title }}</h1>
        <div>{!! $vacancy->description !!}</div>
        {{-- ... інший вміст --}}
    </article>

    @if($isExpired && $similar->isNotEmpty())
        <section class="mt-12 border-t pt-8">
            <h2 class="text-xl font-semibold mb-6">Схожі активні вакансії</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($similar as $item)
                    <x-vacancy.card :vacancy="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-app-layout>
```

---

## 🧩 Blade-компонент банера

`resources/views/components/vacancy/expired-banner.blade.php`:

```blade
@props(['vacancy'])

<div class="rounded-lg bg-yellow-50 border border-yellow-200 p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div class="flex-1">
            <h3 class="font-medium text-yellow-900">Ця вакансія неактивна</h3>
            <p class="text-sm text-yellow-700 mt-1">
                Публікація закрита {{ $vacancy->expires_at?->locale('uk')->isoFormat('D MMMM YYYY') }}.
                Перегляньте схожі активні вакансії нижче.
            </p>
        </div>
    </div>
</div>
```

---

## ⚠️ Нюанси

1. **`noindex, follow`** (а не `noindex, nofollow`) — щоб краулери все ще ходили по лінкам у тілі вакансії, передаючи PageRank на схожі активні.

2. **`validThrough` обов'язково** — Google Jobs використовує це поле, щоб ховати expired з результатів. Без нього вакансія може показуватись як активна тижнями.

3. **404 для archived, а не 410** — `410 Gone` сигналить «не повертайся ніколи». `404` дає індексу шанс перевірити пізніше. Для archived доречний `410`, але `404` — простіший і безпечніший дефолт.

4. **Не редіректь з expired на лендинг категорії** — це втрачає історичний контекст і шкодить UX (юзер прийшов саме за цією вакансією).

5. **Sitemap.xml** — окремий питання. Expired НЕ повинні бути в sitemap. Це поза цим модулем.

---

## ✅ Результат

- Контролер обробляє три статуси (Active/Expired/Archived) різно.
- noindex для expired, 404 для archived.
- Schema.org JobPosting з validThrough.
- Банер + блок «Схожі активні».
- Перейти до модуля 10 (тести).
