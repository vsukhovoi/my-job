<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaticPagesSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function about_page_has_unique_title(): void
    {
        $response = $this->get('/about')->assertOk();

        $this->assertStringContainsString('Про нас — My Job', $response->getContent());
        $this->assertStringNotContainsString(
            '<title>' . config('app.name') . '</title>',
            $response->getContent(),
        );
    }

    #[Test]
    public function about_page_has_self_canonical(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . url('/about') . '">', escape: false);
    }

    #[Test]
    public function about_page_has_full_og_set(): void
    {
        $content = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('og:image', $content);
        $this->assertStringContainsString('og:type', $content);
        $this->assertStringContainsString('og:url', $content);
        $this->assertStringContainsString('twitter:card', $content);
    }

    #[Test]
    public function contacts_page_has_self_canonical_and_unique_title(): void
    {
        $content = $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('Контакти та підтримка — My Job', $content);
        $this->assertStringContainsString(
            '<link rel="canonical" href="' . url('/contacts') . '">',
            $content,
        );
    }

    #[Test]
    public function sitemap_includes_active_vacancy_and_excludes_inactive(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);

        $active = Vacancy::factory()->active()->create([
            'company_id'       => $company->id,
            'publication_type' => VacancyPublicationType::Standard,
        ]);

        $inactive = Vacancy::factory()->create([
            'company_id'       => $company->id,
            'status'           => VacancyStatus::Draft,
            'publication_type' => VacancyPublicationType::Standard,
        ]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString($active->slug, $content);
        $this->assertStringNotContainsString($inactive->slug, $content);
    }

    #[Test]
    public function sitemap_excludes_anonymous_vacancies(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);

        $anonymous = Vacancy::factory()->active()->create([
            'company_id'       => $company->id,
            'publication_type' => VacancyPublicationType::Anonymous,
        ]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString($anonymous->slug, $content);
    }

    #[Test]
    public function sitemap_includes_static_pages_and_excludes_offer(): void
    {
        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/about'), $content);
        $this->assertStringContainsString(url('/contacts'), $content);
        $this->assertStringNotContainsString(url('/offer'), $content);
    }
}
