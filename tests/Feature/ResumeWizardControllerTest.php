<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailVerification;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeWizardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/resumes')->assertStatus(401);
    }

    #[Test]
    public function it_lists_resumes_for_authenticated_user(): void
    {
        Resume::factory()->for($this->user)->count(3)->create();
        Resume::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/resumes');

        $response->assertOk();
        $this->assertEquals(3, $response->json('count'));
    }

    #[Test]
    public function it_creates_a_resume(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/resumes', ['title' => 'My First Resume']);

        $response->assertCreated();
        $this->assertDatabaseHas('resumes', [
            'user_id' => $this->user->id,
            'title'   => 'My First Resume',
            'status'  => 'draft',
        ]);
    }

    #[Test]
    public function it_shows_own_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->getJson("/api/resumes/{$resume->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $resume->id);
    }

    #[Test]
    public function it_forbids_viewing_other_users_resume(): void
    {
        $resume = Resume::factory()->create();

        $this->actingAs($this->user)
            ->getJson("/api/resumes/{$resume->id}")
            ->assertForbidden();
    }

    #[Test]
    public function it_updates_resume_personal_info(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)
            ->patchJson("/api/resumes/{$resume->id}", [
                'personal_info' => ['first_name' => 'Іван', 'last_name' => 'Петренко'],
            ]);

        $response->assertOk();
        $this->assertEquals('Іван', $response->json('data.personal_info.first_name'));
    }

    #[Test]
    public function it_sends_verification_code(): void
    {
        Mail::fake();

        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/send-verification-code", [
                'email' => 'test@example.com',
            ])
            ->assertOk();

        $this->assertDatabaseHas('email_verifications', ['email' => 'test@example.com']);
    }

    #[Test]
    public function it_verifies_email_with_correct_code(): void
    {
        Mail::fake();

        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/send-verification-code", [
                'email' => 'test@example.com',
            ]);

        $code = EmailVerification::where('email', 'test@example.com')->value('code');

        $response = $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/verify-email", [
                'email' => 'test@example.com',
                'code'  => $code,
            ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.personal_info.email_verified_at'));
    }

    #[Test]
    public function it_rejects_invalid_verification_code(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/verify-email", [
                'email' => 'test@example.com',
                'code'  => '000000',
            ])
            ->assertStatus(404);
    }

    #[Test]
    public function it_adds_experience_to_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/experiences", [
                'position'     => 'Senior Developer',
                'company_name' => 'TechCorp',
                'start_date'   => '2020-01-15',
                'end_date'     => '2023-06-30',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('experiences', [
            'resume_id' => $resume->id,
            'position'  => 'Senior Developer',
        ]);
    }

    #[Test]
    public function it_prevents_more_than_5_experiences(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        for ($i = 0; $i < 5; $i++) {
            $resume->experiences()->create([
                'position'     => "Job {$i}",
                'company_name' => "Company {$i}",
                'start_date'   => now()->subYears(6 - $i),
                'end_date'     => now()->subYears(5 - $i),
            ]);
        }

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/experiences", [
                'position'     => 'Job 6',
                'company_name' => 'Company 6',
                'start_date'   => '2010-01-01',
                'end_date'     => '2011-01-01',
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_adds_skill_to_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/skills", ['skill_name' => 'Laravel'])
            ->assertCreated();

        $this->assertDatabaseHas('skills', [
            'resume_id'  => $resume->id,
            'skill_name' => 'Laravel',
        ]);
    }

    #[Test]
    public function it_deletes_skill_from_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create();
        $skill  = $resume->skills()->create(['skill_name' => 'Laravel']);

        $this->actingAs($this->user)
            ->deleteJson("/api/resumes/{$resume->id}/skills/{$skill->id}")
            ->assertOk();

        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }

    #[Test]
    public function it_publishes_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create([
            'personal_info' => [
                'first_name'        => 'Іван',
                'last_name'         => 'Петренко',
                'email'             => 'ivan@example.com',
                'email_verified_at' => now()->toIso8601String(),
            ],
        ]);

        $resume->experiences()->create([
            'position'     => 'Developer',
            'company_name' => 'TechCorp',
            'start_date'   => now()->subYear(),
            'end_date'     => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/publish");

        $response->assertOk();
        $this->assertEquals('published', $response->json('data.status'));
    }

    #[Test]
    public function it_prevents_publishing_incomplete_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson("/api/resumes/{$resume->id}/publish")
            ->assertUnprocessable();
    }

    #[Test]
    public function it_prevents_deleting_published_resume(): void
    {
        $resume = Resume::factory()->for($this->user)->published()->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/resumes/{$resume->id}")
            ->assertForbidden();
    }

    #[Test]
    public function it_returns_stepper_status(): void
    {
        $resume = Resume::factory()->for($this->user)->create([
            'personal_info' => [
                'first_name'        => 'Іван',
                'last_name'         => 'Петренко',
                'email'             => null,
                'email_verified_at' => null,
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/resumes/{$resume->id}/stepper-status");

        $response->assertOk();
        $this->assertTrue($response->json('data.personal_info'));
        $this->assertTrue($response->json('data.auth'));
    }
}
