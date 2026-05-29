<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\View\Components\BreadcrumbListSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveVacancy(): Vacancy
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);

        return Vacancy::factory()->active()->create([
            'company_id'      => $company->id,
            'employment_type' => ['full-time'],
        ]);
    }

    #[Test]
    public function vacancy_page_contains_breadcrumb_list_schema(): void
    {
        $vacancy = $this->makeActiveVacancy();

        $this->get("/jobs/{$vacancy->slug}")
            ->assertOk()
            ->assertSee('"@type": "BreadcrumbList"', escape: false);
    }

    #[Test]
    public function breadcrumb_list_has_correct_item_count_and_positions(): void
    {
        $items = [
            ['name' => 'Вакансії',   'url' => 'https://example.com/'],
            ['name' => 'IT',         'url' => 'https://example.com/?categoryId=1'],
            ['name' => 'PHP Dev',    'url' => 'https://example.com/jobs/php-dev'],
        ];

        $component = new BreadcrumbListSchema($items);

        $elements = $component->ldJson['itemListElement'];

        $this->assertCount(3, $elements);
        $this->assertSame(1, $elements[0]['position']);
        $this->assertSame(2, $elements[1]['position']);
        $this->assertSame(3, $elements[2]['position']);
    }

    #[Test]
    public function each_breadcrumb_item_has_absolute_url_and_non_empty_name(): void
    {
        $items = [
            ['name' => 'Вакансії', 'url' => url('/')],
            ['name' => 'IT',       'url' => url('/?categoryId=1')],
            ['name' => 'PHP Dev',  'url' => url('/jobs/php-dev')],
        ];

        $component = new BreadcrumbListSchema($items);

        foreach ($component->ldJson['itemListElement'] as $element) {
            $this->assertNotEmpty($element['name']);
            $this->assertStringStartsWith('http', $element['item']);
        }
    }

    #[Test]
    public function breadcrumb_schema_levels_match_html_breadcrumb(): void
    {
        $vacancy = $this->makeActiveVacancy();
        $vacancy->load('category');

        $response = $this->get("/jobs/{$vacancy->slug}");
        $content  = $response->getContent();

        $response->assertOk();

        $this->assertStringContainsString($vacancy->category->name, $content);
        $this->assertStringContainsString($vacancy->title, $content);
        $this->assertStringContainsString('"BreadcrumbList"', $content);
    }

    #[Test]
    public function home_page_contains_website_schema_with_search_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('"@type": "WebSite"', escape: false)
            ->assertSee('"@type": "SearchAction"', escape: false);
    }

    #[Test]
    public function search_action_target_contains_placeholder_and_query_input(): void
    {
        $response = $this->get('/');
        $content  = $response->getContent();

        $response->assertOk();

        $this->assertStringContainsString('{search_term_string}', $content);
        $this->assertStringContainsString('required name=search_term_string', $content);
    }

    #[Test]
    public function website_schema_is_absent_on_vacancy_page(): void
    {
        $vacancy = $this->makeActiveVacancy();

        $this->get("/jobs/{$vacancy->slug}")
            ->assertOk()
            ->assertDontSee('"@type": "WebSite"', escape: false);
    }
}
