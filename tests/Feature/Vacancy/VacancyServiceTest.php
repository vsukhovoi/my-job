<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Enums\PlanType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\EmployerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\VacancyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VacancyServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::factory()->create();
    }

    private function makeEmployer(): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer, 'phone' => '+380991234567']);
        $company  = Company::factory()->create([
            'user_id'     => $employer->id,
            'logo'        => 'logos/test.png',
            'description' => 'Опис компанії для тесту публікації вакансій.',
            'edrpou'      => '12345678',
            'website'     => 'https://example.com',
            'location'    => 'Київ',
        ]);

        return [$employer, $company];
    }

    private function baseData(int $companyId): array
    {
        return [
            'company_id'      => $companyId,
            'category_id'     => $this->category->id,
            'title'           => 'Test Vacancy',
            'description'     => str_repeat('a', 50),
            'employment_type' => ['full_time'],
            'currency'        => 'UAH',
        ];
    }

    private function activatePlan(User $employer, string $planType): void
    {
        $defaults = [
            'business' => ['active_jobs' => 10, 'applications_per_month' => 0, 'analytics' => true, 'message_templates' => true, 'hot_per_month' => 1, 'top_per_month' => 0, 'api_access' => false, 'team_members' => 3],
            'pro'      => ['active_jobs' => 0,  'applications_per_month' => 0, 'analytics' => true, 'message_templates' => true, 'hot_per_month' => 3, 'top_per_month' => 1, 'api_access' => true,  'team_members' => 0],
        ];

        $plan = SubscriptionPlan::create([
            'type'          => $planType,
            'name'          => ucfirst($planType),
            'price_monthly' => $planType === 'business' ? 1990 : 4490,
            'features'      => $defaults[$planType],
        ]);

        EmployerSubscription::create([
            'user_id'   => $employer->id,
            'plan_id'   => $plan->id,
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addMonth(),
        ]);
    }

    #[Test]
    public function publish_sets_expires_at_30_days_for_free_employer(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $vacancy = $service->publish($employer, $this->baseData($company->id));

        $this->assertSame(now()->addDays(30)->toDateString(), $vacancy->expires_at->toDateString());
    }

    #[Test]
    public function publish_sets_expires_at_60_days_for_business_employer(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $this->activatePlan($employer, 'business');
        $service = $this->app->make(VacancyService::class);

        $vacancy = $service->publish($employer->fresh(), $this->baseData($company->id));

        $this->assertSame(now()->addDays(60)->toDateString(), $vacancy->expires_at->toDateString());
    }

    #[Test]
    public function publish_sets_expires_at_60_days_for_pro_employer(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $this->activatePlan($employer, 'pro');
        $service = $this->app->make(VacancyService::class);

        $vacancy = $service->publish($employer->fresh(), $this->baseData($company->id));

        $this->assertSame(now()->addDays(60)->toDateString(), $vacancy->expires_at->toDateString());
    }

    #[Test]
    public function publish_generates_unique_slug_from_title(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $data    = array_merge($this->baseData($company->id), ['title' => 'Laravel Developer']);
        $vacancy = $service->publish($employer, $data);

        $this->assertSame('laravel-developer', $vacancy->slug);
    }

    #[Test]
    public function publish_adds_suffix_when_slug_already_exists(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $data = array_merge($this->baseData($company->id), ['title' => 'Повар']);

        $first  = $service->publish($employer, $data);
        $second = $service->publish($employer, $data);

        $this->assertSame('povar', $first->slug);
        $this->assertSame('povar-1', $second->slug);
    }

    #[Test]
    public function update_regenerates_slug_when_title_changes(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $vacancy = $service->publish($employer, $this->baseData($company->id));
        $this->assertSame('test-vacancy', $vacancy->slug);

        $updated = $service->update($vacancy, array_merge($this->baseData($company->id), ['title' => 'New Title Here']));

        $this->assertSame('new-title-here', $updated->slug);
    }

    #[Test]
    public function update_keeps_slug_when_title_unchanged(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $vacancy    = $service->publish($employer, $this->baseData($company->id));
        $originSlug = $vacancy->slug;

        $updated = $service->update($vacancy, $this->baseData($company->id));

        $this->assertSame($originSlug, $updated->slug);
    }

    #[Test]
    public function update_does_not_change_expires_at(): void
    {
        [$employer, $company] = $this->makeEmployer();
        $service = $this->app->make(VacancyService::class);

        $vacancy          = $service->publish($employer, $this->baseData($company->id));
        $originalExpiresAt = $vacancy->expires_at->copy();

        Carbon::setTestNow(now()->addDays(5));

        $updated = $service->update($vacancy, array_merge($this->baseData($company->id), ['title' => 'Updated Title']));

        Carbon::setTestNow();

        $this->assertTrue($originalExpiresAt->eq($updated->expires_at));
    }
}
