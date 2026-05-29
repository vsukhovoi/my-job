<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\VacancySearchDTO;
use App\Enums\PlanType;
use App\Enums\VacancyStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VacancyService
{
    private const PROFILE_ACTIVATION_THRESHOLD = 90;

    public function __construct(
        private readonly ProfileCompletenessService $completeness,
    ) {}

    public function publish(User $employer, array $data): Vacancy
    {
        $slug      = $this->generateSlug($data['title']);
        $expiresAt = $this->getExpiresAt($employer);

        $data['published_at'] ??= now();
        $data['status']       ??= VacancyStatus::Active;

        $vacancy = Vacancy::create([...$data, 'slug' => $slug, 'expires_at' => $expiresAt]);

        $this->maybeActivateFreePlan($employer);

        return $vacancy;
    }

    private function maybeActivateFreePlan(User $employer): void
    {
        if ($employer->currentPlan() !== null) {
            return;
        }

        $freePlan = SubscriptionPlan::where('type', PlanType::Free)->first();

        if ($freePlan) {
            app(SubscriptionService::class)->activate($employer, $freePlan);
        }
    }

    public function update(Vacancy $vacancy, array $data): Vacancy
    {
        $slug = isset($data['title']) && $data['title'] !== $vacancy->title
            ? $this->generateSlug($data['title'], $vacancy->id)
            : $vacancy->slug;

        $vacancy->update([...$data, 'slug' => $slug]);

        if (! empty($data['is_active'])) {
            $employer = $vacancy->company?->user;
            if ($employer) {
                app(SubscriptionService::class)->activateFreePlanIfNeeded($employer);
            }
        }

        return $vacancy->refresh();
    }

    public function getExpiresAt(User $employer): Carbon
    {
        $plan    = $employer->currentPlan();
        $company = $employer->company;

        $score = $company ? $this->completeness->employerScore($employer)['score'] : 0;

        return match ($plan?->type) {
            PlanType::Business, PlanType::Pro => now()->addDays(60),
            default => $score >= self::PROFILE_ACTIVATION_THRESHOLD
                ? now()->addDays(30)
                : now()->addDay(),
        };
    }

    public function generateSlug(string $title, ?int $excludeId = null): string
    {
        $base    = Str::slug($title);
        $slug    = $base;
        $counter = 1;

        while (
            Vacancy::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }


    /**
     * Search and filter active vacancies.
     * Featured vacancies are always sorted first.
     */
    public function search(VacancySearchDTO $dto): LengthAwarePaginator
    {
        return Vacancy::query()
            ->with(['company', 'category', 'city'])
            ->active()
            ->when($dto->search, function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($dto->categoryId, fn(Builder $q, int $id): Builder => $q->where('category_id', $id))
            ->when($dto->employmentTypes, function (Builder $q, array $types): void {
                $q->where(function (Builder $inner) use ($types): void {
                    foreach ($types as $type) {
                        $inner->orWhereJsonContains('employment_type', $type);
                    }
                });
            })
            ->when($dto->salaryMin, fn(Builder $q, int $min): Builder => $q->where('salary_from', '>=', $min))
            ->when($dto->salaryMax, fn(Builder $q, int $max): Builder => $q->where('salary_to', '<=', $max))
            ->when($dto->languages, function (Builder $q, array $languages): void {
                foreach ($languages as $lang) {
                    $q->whereJsonContains('languages', $lang);
                }
            })
            ->when($dto->suitability, function (Builder $q, array $suitability): void {
                foreach ($suitability as $item) {
                    $q->whereJsonContains('suitability', $item);
                }
            })
            ->when($dto->cityId, fn(Builder $q, int $id): Builder => $q->where('city_id', $id))
            ->when($dto->companyId, fn(Builder $q, int $id): Builder => $q->where('company_id', $id))
            ->orderByDesc('is_top')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate($dto->perPage);
    }
}
