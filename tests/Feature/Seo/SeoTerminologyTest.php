<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\SeoService;
use App\View\Components\JobPostingSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoTerminologyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_title_does_not_contain_forbidden_phrase(): void
    {
        $seo = app(SeoService::class)->forHome();

        $this->assertStringNotContainsStringIgnoringCase('Пошук роботи', $seo['title']);
        $this->assertStringContainsString('Оголошення про роботу', $seo['title']);
    }

    #[Test]
    public function home_description_does_not_contain_forbidden_phrases(): void
    {
        $seo = app(SeoService::class)->forHome();

        $this->assertStringNotContainsStringIgnoringCase('Знайдіть роботу', $seo['description']);
        $this->assertStringNotContainsStringIgnoringCase('Пошук роботи', $seo['description']);
    }

    #[Test]
    public function layout_fallback_description_does_not_contain_forbidden_phrase(): void
    {
        $response = $this->get('/about');

        $content = $response->getContent();

        $this->assertStringNotContainsStringIgnoringCase('Пошук роботи в Україні', $content);
    }

    #[Test]
    public function footer_does_not_contain_forbidden_link_text(): void
    {
        $response = $this->get('/');

        $this->assertStringNotContainsString('Пошук вакансій', $response->getContent());
        $this->assertStringContainsString('Перегляд вакансій', $response->getContent());
    }

    #[Test]
    public function job_posting_schema_contains_url_field(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy  = Vacancy::factory()->active()->create([
            'company_id'      => $company->id,
            'employment_type' => ['full-time'],
        ]);

        $component = new JobPostingSchema($vacancy->load('company', 'city'));

        $this->assertArrayHasKey('url', $component->ldJson);
        $this->assertStringContainsString("/jobs/{$vacancy->slug}", $component->ldJson['url']);
    }

    #[Test]
    public function job_posting_schema_contains_image_for_non_anonymous_with_logo(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create([
            'user_id' => $employer->id,
            'logo'    => 'logos/test.webp',
        ]);
        $vacancy = Vacancy::factory()->active()->create([
            'company_id'       => $company->id,
            'employment_type'  => ['full-time'],
            'publication_type' => \App\Enums\VacancyPublicationType::Standard,
        ]);

        $component = new JobPostingSchema($vacancy->load('company', 'city'));

        $this->assertArrayHasKey('image', $component->ldJson);

        $anonymousVacancy = Vacancy::factory()->active()->create([
            'company_id'      => $company->id,
            'employment_type' => ['full-time'],
            'publication_type' => \App\Enums\VacancyPublicationType::Anonymous,
        ]);

        $anonymousComponent = new JobPostingSchema($anonymousVacancy->load('company', 'city'));

        $this->assertArrayNotHasKey('image', $anonymousComponent->ldJson);
    }

    #[Test]
    public function job_posting_schema_contains_identifier(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy  = Vacancy::factory()->active()->create([
            'company_id'      => $company->id,
            'employment_type' => ['full-time'],
        ]);

        $component = new JobPostingSchema($vacancy->load('company', 'city'));

        $this->assertArrayHasKey('identifier', $component->ldJson);
        $this->assertSame('PropertyValue', $component->ldJson['identifier']['@type']);
        $this->assertSame($vacancy->id, $component->ldJson['identifier']['value']);
    }
}
