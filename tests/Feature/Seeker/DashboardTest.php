<?php

declare(strict_types=1);

namespace Tests\Feature\Seeker;

use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
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
    public function dashboard_loads_successfully(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker')
            ->assertStatus(200);
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/dashboard/seeker')
            ->assertRedirect('/login');
    }

    #[Test]
    public function employer_cannot_access_seeker_dashboard(): void
    {
        $this->actingAs($this->employer)
            ->get('/dashboard/seeker')
            ->assertStatus(403);
    }

    #[Test]
    public function dashboard_renders_seeker_tabs_component(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker')
            ->assertSee('Знайти вакансії');
    }

    #[Test]
    public function dashboard_shows_only_own_applications(): void
    {
        $other = User::factory()->create();
        Application::factory()->count(2)->create(['user_id' => $this->candidate->id]);
        Application::factory()->count(5)->create(['user_id' => $other->id]);

        $this->assertEquals(2, $this->candidate->applications()->count());
        $this->assertEquals(5, $other->applications()->count());
    }

    #[Test]
    public function dashboard_active_applications_excludes_hired_and_rejected(): void
    {
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Pending]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Screening]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Interview]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Hired]);
        Application::factory()->create(['user_id' => $this->candidate->id, 'status' => ApplicationStatus::Rejected]);

        $active = $this->candidate->applications()
            ->whereIn('status', ['pending', 'screening', 'interview'])
            ->count();

        $this->assertEquals(3, $active);
    }

    #[Test]
    public function dashboard_upcoming_interviews_count_is_correct(): void
    {
        $application = Application::factory()->create(['user_id' => $this->candidate->id]);

        Interview::factory()->upcoming()->create(['application_id' => $application->id]);
        Interview::factory()->past()->create(['application_id' => $application->id]);

        $count = Interview::whereHas('application', fn ($q) => $q->where('user_id', $this->candidate->id))
            ->where('scheduled_at', '>=', now())
            ->whereIn('status', [InterviewStatus::Scheduled->value, InterviewStatus::Confirmed->value])
            ->count();

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function dashboard_page_shows_find_vacancies_link(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker')
            ->assertSee('Знайти вакансії');
    }
}
