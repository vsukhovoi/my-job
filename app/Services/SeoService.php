<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vacancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SeoService
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * @return array<string, string>
     */
    public function forHome(): array
    {
        return Cache::remember('seo:home', self::CACHE_TTL, function (): array {
            $title = config('app.name') . ' — Пошук роботи в Україні';

            return [
                'title'          => $title,
                'description'    => 'Тисячі вакансій по всій Україні. Знайдіть роботу в IT, продажах, медицині, маркетингу та інших сферах. Фільтр за містом, зарплатою та типом зайнятості.',
                'og_title'       => $title,
                'og_description' => 'Тисячі вакансій по всій Україні — IT, продажі, маркетинг, медицина та інші сфери.',
                'og_image'       => asset('img/logo/mj-logo-1300x1300.webp'),
                'canonical'      => url('/'),
            ];
        });
    }

    /**
     * @return array<string, string>
     */
    public function forVacancy(Vacancy $vacancy): array
    {
        return Cache::remember("seo:vacancy:{$vacancy->id}", self::CACHE_TTL, function () use ($vacancy): array {
            $title       = "{$vacancy->title} — {$vacancy->company->name} | " . config('app.name');
            $description = Str::limit(strip_tags($vacancy->description), 160);
            $salary      = $vacancy->salary_from
                ? "Salary: {$vacancy->salary_from}–{$vacancy->salary_to} {$vacancy->currency}. "
                : '';

            return [
                'title'          => $title,
                'description'    => $salary . $description,
                'og_title'       => "{$vacancy->title} — {$vacancy->company->name}",
                'og_description' => $description,
                'og_image'       => $vacancy->company->logo_url ?? asset('img/logo/mj-logo-1300x1300.webp'),
                'canonical'      => url("/jobs/{$vacancy->slug}"),
            ];
        });
    }
}
