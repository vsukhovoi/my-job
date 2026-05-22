<?php

declare(strict_types=1);

namespace Tests\Livewire\ResumeSteps;

use App\Livewire\ResumeSteps\CardStep;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CardStepTest extends TestCase
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
    public function it_renders_card_step(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->assertOk()
            ->assertSee('Ваша картка-візитка');
    }

    #[Test]
    public function it_shows_validation_errors_on_blur_with_empty_fields(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->call('onValidate')
            ->assertSet('errors.first_name', "Ім'я обов'язкове")
            ->assertSet('errors.last_name',  "Прізвище обов'язкове");
    }

    #[Test]
    public function it_clears_errors_when_fields_are_filled(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->set('formData.personal_info.first_name', 'Іван')
            ->set('formData.personal_info.last_name',  'Петренко')
            ->call('onValidate')
            ->assertSet('errors', []);
    }

    #[Test]
    public function it_dispatches_update_form_data_on_valid_data(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->set('formData.personal_info.first_name', 'Іван')
            ->set('formData.personal_info.last_name',  'Петренко')
            ->assertDispatched('updateFormData');
    }

    #[Test]
    public function it_updates_privacy_flag(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->set('formData.personal_info.privacy', true)
            ->call('updatePrivacy')
            ->assertSet('formData.personal_info.privacy', true)
            ->assertDispatched('updateFormData');
    }

    #[Test]
    public function it_updates_transparency_flag(): void
    {
        Livewire::test(CardStep::class, ['resume' => $this->resume])
            ->set('formData.personal_info.transparency', true)
            ->call('updateTransparency')
            ->assertSet('formData.personal_info.transparency', true)
            ->assertDispatched('updateFormData');
    }
}
