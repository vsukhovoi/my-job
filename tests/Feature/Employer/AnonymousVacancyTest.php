<?php

declare(strict_types=1);

namespace Tests\Feature\Employer;

use App\Enums\UserRole;
use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\PaymentResult;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnonymousVacancyTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;
    private Company $company;
    private Vacancy $vacancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->create(['role' => UserRole::Employer]);
        $this->company  = Company::factory()->create([
            'user_id' => $this->employer->id,
            'name'    => 'ТОВ "Тестова Компанія"',
        ]);
        $this->vacancy  = Vacancy::factory()->create([
            'company_id'       => $this->company->id,
            'status'           => VacancyStatus::Active,
            'publication_type' => VacancyPublicationType::Standard,
        ]);
    }

    #[Test]
    public function vacancy_defaults_to_standard_publication_type(): void
    {
        $this->assertEquals(VacancyPublicationType::Standard, $this->vacancy->publication_type);
        $this->assertFalse($this->vacancy->isAnonymous());
    }

    #[Test]
    public function anonymous_vacancy_shows_default_name_when_no_pseudonym(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'anonymous_name'   => null,
        ]);

        $this->assertEquals('Компанія', $this->vacancy->fresh()->display_company_name);
    }

    #[Test]
    public function anonymous_vacancy_shows_custom_pseudonym(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'anonymous_name'   => 'Великий Бізнес',
        ]);

        $this->assertEquals('Великий Бізнес', $this->vacancy->fresh()->display_company_name);
    }

    #[Test]
    public function standard_vacancy_shows_real_company_name(): void
    {
        $this->assertEquals(
            $this->company->name,
            $this->vacancy->display_company_name
        );
    }

    #[Test]
    public function anonymous_vacancy_excluded_from_company_public_profile(): void
    {
        $this->vacancy->update(['publication_type' => VacancyPublicationType::Anonymous]);

        $publicVacancies = Vacancy::where('company_id', $this->company->id)
            ->where('status', VacancyStatus::Active)
            ->where('publication_type', VacancyPublicationType::Standard)
            ->get();

        $this->assertCount(0, $publicVacancies);
    }

    #[Test]
    public function auto_refresh_command_updates_published_at(): void
    {
        $oldDate = now()->subDays(7);

        $this->vacancy->update([
            'publication_type'   => VacancyPublicationType::Anonymous,
            'auto_refresh'       => true,
            'auto_refresh_until' => now()->addDays(30),
            'published_at'       => $oldDate,
        ]);

        $this->artisan('vacancies:refresh-anonymous')->assertSuccessful();

        $this->assertGreaterThan(
            $oldDate,
            $this->vacancy->fresh()->published_at
        );
    }

    #[Test]
    public function expired_auto_refresh_does_not_update_vacancy(): void
    {
        $oldDate = now()->subDays(7);

        $this->vacancy->update([
            'publication_type'   => VacancyPublicationType::Anonymous,
            'auto_refresh'       => true,
            'auto_refresh_until' => now()->subDay(),
            'published_at'       => $oldDate,
        ]);

        $this->artisan('vacancies:refresh-anonymous')->assertSuccessful();

        $this->assertEquals(
            $oldDate->toDateTimeString(),
            $this->vacancy->fresh()->published_at->toDateTimeString()
        );
    }

    #[Test]
    public function webhook_activates_anonymous_vacancy_on_paid_result(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'is_active'        => false,
        ]);

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('name')->willReturn('mono');
        $gateway->method('parseWebhook')->willReturn(new PaymentResult(
            isPaid:             true,
            gatewayName:        'mono',
            externalEventId:    'evt_test_anon_001',
            orderId:            'anon_' . $this->vacancy->id . '_abc123',
            amountKopecks:      59900,
            currency:           'UAH',
            vacancyId:          null,
            days:               null,
            anonymousVacancyId: $this->vacancy->id,
        ));
        $gateway->method('successResponse')->willReturn(response(''));

        $registry = $this->createMock(PaymentGatewayRegistry::class);
        $registry->method('get')->willReturn($gateway);

        $this->app->instance(PaymentGatewayRegistry::class, $registry);

        $this->postJson('/webhooks/payments/mono', []);

        $this->assertTrue($this->vacancy->fresh()->is_active);
    }

    #[Test]
    public function send_vacancy_alerts_hides_company_name_for_anonymous(): void
    {
        \App\Models\TelegramSubscription::factory()->create([
            'category_id' => $this->vacancy->category_id,
            'telegram_id' => '999999999',
        ]);

        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'anonymous_name'   => 'Велика Компанія',
            'is_active'        => true,
            'published_at'     => now(),
        ]);

        $notifier = $this->createMock(TelegramNotifier::class);
        $notifier->expects($this->once())
            ->method('send')
            ->with(
                '999999999',
                $this->callback(fn(string $text) =>
                    str_contains($text, 'Велика Компанія') &&
                    ! str_contains($text, 'ТОВ "Тестова Компанія"')
                )
            );

        $this->app->instance(TelegramNotifier::class, $notifier);
        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();
    }

    #[Test]
    public function send_vacancy_alerts_shows_real_company_name_for_standard(): void
    {
        \App\Models\TelegramSubscription::factory()->create([
            'category_id' => $this->vacancy->category_id,
            'telegram_id' => '888888888',
        ]);

        $this->vacancy->update([
            'is_active'    => true,
            'published_at' => now(),
        ]);

        $notifier = $this->createMock(TelegramNotifier::class);
        $notifier->expects($this->once())
            ->method('send')
            ->with(
                '888888888',
                $this->callback(fn(string $text) =>
                    str_contains($text, 'ТОВ "Тестова Компанія"')
                )
            );

        $this->app->instance(TelegramNotifier::class, $notifier);
        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();
    }

    #[Test]
    public function webhook_does_not_activate_vacancy_when_payment_failed(): void
    {
        $this->vacancy->update([
            'publication_type' => VacancyPublicationType::Anonymous,
            'is_active'        => false,
        ]);

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('name')->willReturn('mono');
        $gateway->method('parseWebhook')->willReturn(new PaymentResult(
            isPaid:             false,
            gatewayName:        'mono',
            externalEventId:    'evt_test_anon_002',
            orderId:            'anon_' . $this->vacancy->id . '_abc456',
            amountKopecks:      59900,
            currency:           'UAH',
            vacancyId:          null,
            days:               null,
            anonymousVacancyId: $this->vacancy->id,
            failureReason:      'status=failure',
        ));
        $gateway->method('successResponse')->willReturn(response(''));

        $registry = $this->createMock(PaymentGatewayRegistry::class);
        $registry->method('get')->willReturn($gateway);

        $this->app->instance(PaymentGatewayRegistry::class, $registry);

        $this->postJson('/webhooks/payments/mono', []);

        $this->assertFalse($this->vacancy->fresh()->is_active);
    }
}
