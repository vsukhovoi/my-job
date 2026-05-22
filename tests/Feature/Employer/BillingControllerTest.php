<?php

declare(strict_types=1);

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.prices' => [15 => 10000, 30 => 20000, 90 => 50000]]);
    }

    private function makeEmployer(): array
    {
        $user    = User::factory()->employer()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        return [$user, $company];
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('employer.billing'))
            ->assertRedirect(route('login'));
    }

    public function test_employer_can_see_billing_page(): void
    {
        [$user] = $this->makeEmployer();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertOk();
    }

    public function test_billing_shows_empty_state_with_no_transactions(): void
    {
        [$user] = $this->makeEmployer();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertOk()
            ->assertDontSee('Історія платежів');
    }

    public function test_billing_shows_own_transactions(): void
    {
        [$user, $company] = $this->makeEmployer();
        $vacancy = Vacancy::factory()->active()->create(['company_id' => $company->id]);
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->create();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertSee('30 днів');
    }

    public function test_billing_does_not_show_other_employer_transactions(): void
    {
        [$user] = $this->makeEmployer();

        $other        = User::factory()->employer()->create();
        $otherCompany = Company::factory()->create(['user_id' => $other->id]);
        $otherVacancy = Vacancy::factory()->active()->create(['company_id' => $otherCompany->id]);
        $otherTx      = PaymentTransaction::factory()->forVacancy($otherVacancy, 90)->create();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertDontSee($otherTx->event_id);
    }

    public function test_stats_total_count_is_correct(): void
    {
        [$user, $company] = $this->makeEmployer();
        $vacancy = Vacancy::factory()->active()->create(['company_id' => $company->id]);
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->count(3)->create();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertSee('3');
    }

    public function test_stats_total_uah_computed_from_config(): void
    {
        [$user, $company] = $this->makeEmployer();
        $vacancy = Vacancy::factory()->active()->create(['company_id' => $company->id]);
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->count(2)->create();

        $expected = number_format(2 * 200, 0, '.', ' ');

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertSee($expected);
    }

    public function test_employer_without_company_sees_empty_state(): void
    {
        $user = User::factory()->employer()->create();

        $this->actingAs($user)
            ->get(route('employer.billing'))
            ->assertOk()
            ->assertDontSee('Історія платежів');
    }
}
