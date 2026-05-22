<?php

declare(strict_types=1);

namespace Tests\Feature\Employer;

use App\Enums\UserRole;
use App\Enums\VacancyStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VacancyToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompleteEmployer(): array
    {
        $employer = User::factory()->create([
            'role'  => UserRole::Employer,
            'phone' => '+380991234567',
        ]);

        $company = Company::factory()->create([
            'user_id'     => $employer->id,
            'name'        => 'ТОВ Тест',
            'logo'        => 'logos/test.png',
            'description' => 'Опис компанії для тесту повноти профілю.',
            'edrpou'      => '12345678',
            'website'     => 'https://example.com',
            'location'    => 'Київ',
        ]);

        return [$employer, $company];
    }

    private function makeIncompleteEmployer(): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);

        $company = Company::factory()->create([
            'user_id'     => $employer->id,
            'name'        => 'ТОВ Тест',
            'logo'        => null,
            'description' => 'Опис є',
            'website'     => null,
            'location'    => 'Київ',
        ]);

        return [$employer, $company];
    }

    #[Test]
    public function toggle_shows_modal_when_profile_incomplete(): void
    {
        [$employer, $company] = $this->makeIncompleteEmployer();

        $vacancy = Vacancy::factory()->create([
            'company_id' => $company->id,
            'status'     => VacancyStatus::Draft,
        ]);

        $this->actingAs($employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $vacancy->id)
            ->assertSet('showProfileModal', true);

        $this->assertEquals(VacancyStatus::Draft, $vacancy->fresh()->status);
    }

    #[Test]
    public function toggle_activates_vacancy_when_profile_complete(): void
    {
        [$employer, $company] = $this->makeCompleteEmployer();

        $vacancy = Vacancy::factory()->create([
            'company_id' => $company->id,
            'status'     => VacancyStatus::Draft,
        ]);

        $this->actingAs($employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $vacancy->id)
            ->assertSet('showProfileModal', false);

        $this->assertEquals(VacancyStatus::Active, $vacancy->fresh()->status);
        $this->assertNotNull($vacancy->fresh()->expires_at);
    }

    #[Test]
    public function toggle_deactivates_active_vacancy_without_checking_profile(): void
    {
        [$employer, $company] = $this->makeIncompleteEmployer();

        $vacancy = Vacancy::factory()->create([
            'company_id' => $company->id,
            'status'     => VacancyStatus::Active,
            'is_active'  => true,
        ]);

        $this->actingAs($employer);

        Volt::test('pages.employer.dashboard')
            ->call('toggleActive', $vacancy->id);

        $this->assertEquals(VacancyStatus::Draft, $vacancy->fresh()->status);
    }

    #[Test]
    public function saving_complete_profile_activates_draft_vacancy(): void
    {
        [$employer, $company] = $this->makeCompleteEmployer();

        $vacancy = Vacancy::factory()->create([
            'company_id' => $company->id,
            'status'     => VacancyStatus::Draft,
        ]);

        $this->actingAs($employer);

        Volt::test('pages.employer.profile')
            ->set('name', $company->name)
            ->set('description', $company->description)
            ->set('website', $company->website)
            ->set('location', $company->location)
            ->set('businessType', 'individual')
            ->set('ipn', '1234567890')
            ->call('save')
            ->assertRedirect(route('employer.dashboard'));

        $this->assertEquals(VacancyStatus::Active, $vacancy->fresh()->status);
    }

    #[Test]
    public function saving_incomplete_profile_does_not_activate_vacancy(): void
    {
        [$employer, $company] = $this->makeIncompleteEmployer();

        $vacancy = Vacancy::factory()->create([
            'company_id' => $company->id,
            'status'     => VacancyStatus::Draft,
        ]);

        $this->actingAs($employer);

        Volt::test('pages.employer.profile')
            ->set('name', $company->name)
            ->set('description', $company->description)
            ->set('businessType', 'individual')
            ->set('ipn', '1234567890')
            ->call('save');

        $this->assertEquals(VacancyStatus::Draft, $vacancy->fresh()->status);
    }
}
