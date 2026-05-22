<?php

declare(strict_types=1);

namespace Tests\Livewire;

use App\Livewire\ResumeWizard;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeWizardTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private Resume $resume;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->resume = Resume::factory()->for($this->user)->create();
    }

    #[Test]
    public function it_renders_the_wizard(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->assertOk()
            ->assertSee('Конструктор резюме');
    }

    #[Test]
    public function it_starts_on_step_one(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->assertSet('currentStep', 1);
    }

    #[Test]
    public function it_navigates_to_next_step_with_valid_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->set('formData.personal_info.first_name', 'Іван')
            ->set('formData.personal_info.last_name', 'Петренко')
            ->call('nextStep')
            ->assertSet('currentStep', 2);
    }

    #[Test]
    public function it_prevents_next_step_without_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('nextStep')
            ->assertSet('currentStep', 1);
    }

    #[Test]
    public function it_navigates_to_specific_step(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('goToStep', 4)
            ->assertSet('currentStep', 4);
    }

    #[Test]
    public function it_ignores_invalid_step_numbers(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('goToStep', 0)
            ->assertSet('currentStep', 1)
            ->call('goToStep', 99)
            ->assertSet('currentStep', 1);
    }

    #[Test]
    public function it_navigates_to_previous_step(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->set('formData.personal_info.first_name', 'Іван')
            ->set('formData.personal_info.last_name', 'Петренко')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->call('previousStep')
            ->assertSet('currentStep', 1);
    }

    #[Test]
    public function it_publishes_resume_when_publishable(): void
    {
        $this->resume->updatePersonalInfo([
            'first_name'        => 'Іван',
            'last_name'         => 'Петренко',
            'email'             => 'ivan@example.com',
            'email_verified_at' => now()->toIso8601String(),
        ]);
        $this->resume->experiences()->create([
            'position'     => 'Developer',
            'company_name' => 'TechCorp',
            'start_date'   => now()->subYear(),
            'end_date'     => now(),
        ]);
        $this->resume->refresh();

        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('publishResume')
            ->assertRedirect(route('seeker.resumes'));

        $this->assertEquals('published', $this->resume->fresh()->status);
    }

    #[Test]
    public function it_sets_validation_error_when_resume_not_publishable(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('publishResume')
            ->assertSet('validationErrors.publish', 'Будь ласка, заповніть всі критичні поля перед публікацією');
    }

    #[Test]
    public function it_deletes_draft_resume(): void
    {
        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $this->resume])
            ->call('deleteResume');

        $this->assertDatabaseMissing('resumes', ['id' => $this->resume->id]);
    }

    #[Test]
    public function it_prevents_deleting_published_resume(): void
    {
        $published = Resume::factory()->for($this->user)->published()->create();

        Livewire::actingAs($this->user)
            ->test(ResumeWizard::class, ['resume' => $published])
            ->call('deleteResume')
            ->assertSet('validationErrors.delete', 'Неможливо видалити опубліковане резюме');

        $this->assertDatabaseHas('resumes', ['id' => $published->id]);
    }
}
