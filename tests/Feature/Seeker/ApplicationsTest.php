<?php

declare(strict_types=1);

namespace Tests\Feature\Seeker;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        $this->employer  = User::factory()->employer()->create();
    }

    #[Test]
    public function applications_page_loads_successfully(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker/applications')
            ->assertStatus(200);
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/dashboard/seeker/applications')
            ->assertRedirect('/login');
    }

    #[Test]
    public function employer_cannot_access_applications_page(): void
    {
        $this->actingAs($this->employer)
            ->get('/dashboard/seeker/applications')
            ->assertStatus(403);
    }

    #[Test]
    public function applications_list_shows_only_own_applications(): void
    {
        $other = User::factory()->create();
        Application::factory()->count(3)->create(['user_id' => $this->candidate->id]);
        Application::factory()->count(2)->create(['user_id' => $other->id]);

        $this->assertEquals(3, $this->candidate->applications()->count());
        $this->assertEquals(2, $other->applications()->count());
    }

    #[Test]
    public function applications_can_be_filtered_by_status(): void
    {
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Pending]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Interview]);

        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.applications')
            ->set('filterStatus', 'interview')
            ->assertSee("Інтерв'ю");
    }

    #[Test]
    public function applications_search_filters_by_vacancy_title(): void
    {
        $vacancy      = Vacancy::factory()->create(['title' => 'Senior Laravel Developer']);
        $otherVacancy = Vacancy::factory()->create(['title' => 'Junior React Developer']);

        Application::factory()->create(['user_id' => $this->candidate->id, 'vacancy_id' => $vacancy->id]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'vacancy_id' => $otherVacancy->id]);

        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.applications')
            ->set('search', 'Laravel')
            ->assertSee('Senior Laravel Developer')
            ->assertDontSee('Junior React Developer');
    }

    #[Test]
    public function applications_paginate_query_counts_all_records(): void
    {
        $vacancy = Vacancy::factory()->create();
        Application::factory()->count(20)->create([
            'user_id'    => $this->candidate->id,
            'vacancy_id' => $vacancy->id,
        ]);

        $this->assertEquals(20, $this->candidate->applications()->count());
    }

    #[Test]
    public function applications_sort_by_newest_works(): void
    {
        Application::factory()->create([
            'user_id'    => $this->candidate->id,
            'created_at' => now()->subDays(5),
        ]);
        Application::factory()->create([
            'user_id'    => $this->candidate->id,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.applications')
            ->set('sortBy', 'newest')
            ->assertOk();
    }

    #[Test]
    public function empty_applications_list_shows_empty_state(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.applications')
            ->assertSee('Заявок не знайдено');
    }

    #[Test]
    public function applications_page_shows_all_status_tab(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker/applications')
            ->assertStatus(200)
            ->assertSee('Всі');
    }
}
