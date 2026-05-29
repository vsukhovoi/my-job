<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobsIndexSeoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function base_page_without_filters_is_indexable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('index, follow', escape: false)
            ->assertDontSee('noindex', escape: false);
    }

    #[Test]
    public function page_with_category_filter_has_noindex(): void
    {
        $category = Category::factory()->create();

        $this->get('/?categoryId=' . $category->id)
            ->assertOk()
            ->assertSee('noindex, follow', escape: false);
    }

    #[Test]
    public function page_with_salary_filter_has_noindex(): void
    {
        $this->get('/?salaryMin=10000')
            ->assertOk()
            ->assertSee('noindex, follow', escape: false);
    }

    #[Test]
    public function filtered_page_has_self_referential_canonical(): void
    {
        $category = Category::factory()->create();

        $response = $this->get('/?categoryId=' . $category->id);

        $response->assertOk()
            ->assertSee('categoryId=' . $category->id, escape: false);

        $this->assertStringNotContainsString(
            '<link rel="canonical" href="' . url('/') . '">',
            $response->getContent(),
        );
    }

    #[Test]
    public function pagination_page_has_self_canonical_with_page_param(): void
    {
        $employer  = User::factory()->employer()->create();
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $category  = Category::factory()->create();

        Vacancy::factory()->active()->count(15)->create([
            'company_id'  => $company->id,
            'category_id' => $category->id,
        ]);

        $this->get('/?page=2')
            ->assertOk()
            ->assertSee('page=2', escape: false)
            ->assertSee('index, follow', escape: false);
    }

    #[Test]
    public function has_active_filters_returns_correct_values(): void
    {
        $component = Volt::test('pages.jobs.index');
        $this->assertFalse($component->instance()->hasActiveFilters());

        $component->set('categoryId', '5');
        $this->assertTrue($component->instance()->hasActiveFilters());

        $component->set('categoryId', '');
        $component->set('salaryMin', '20000');
        $this->assertTrue($component->instance()->hasActiveFilters());

        $component->set('salaryMin', '');
        $component->set('companyId', '3');
        $this->assertTrue($component->instance()->hasActiveFilters());
    }

    #[Test]
    public function base_page_canonical_equals_root_url(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . url('/') . '">', escape: false);
    }
}
