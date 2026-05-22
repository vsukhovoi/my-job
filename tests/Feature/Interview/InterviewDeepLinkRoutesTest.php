<?php

declare(strict_types=1);

namespace Tests\Feature\Interview;

use App\Enums\InterviewRequestStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\InterviewRequest;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterviewDeepLinkRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function makeGraph(): array
    {
        $employer    = User::factory()->employer()->create();
        $company     = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy     = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate   = User::factory()->create(['role' => UserRole::Candidate]);
        $application = Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
        ]);
        $request = InterviewRequest::create([
            'application_id'   => $application->id,
            'employer_user_id' => $employer->id,
            'questions'        => ['Розкажіть про досвід?'],
            'deadline_at'      => now()->addDays(3),
            'status'           => InterviewRequestStatus::Pending,
        ]);

        return compact('employer', 'candidate', 'request');
    }

    #[Test]
    public function it_renders_response_form_for_authorized_candidate(): void
    {
        ['candidate' => $candidate, 'request' => $request] = $this->makeGraph();

        $this->actingAs($candidate)
            ->get(route('interview.respond', $request->id))
            ->assertOk();
    }

    #[Test]
    public function it_renders_response_view_for_authorized_employer(): void
    {
        ['employer' => $employer, 'request' => $request] = $this->makeGraph();

        $this->actingAs($employer)
            ->get(route('interview.view', $request->id))
            ->assertOk();
    }

    #[Test]
    public function it_returns_403_for_unauthorized_user_accessing_someone_elses_interview(): void
    {
        ['request' => $request] = $this->makeGraph();

        $stranger = User::factory()->create(['role' => UserRole::Candidate]);

        $this->actingAs($stranger)
            ->get(route('interview.respond', $request->id))
            ->assertForbidden();
    }
}
