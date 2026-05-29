<?php

declare(strict_types=1);

namespace Tests\Feature\Employer;

use App\Enums\AddonType;
use App\Enums\UserRole;
use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Models\Company;
use App\Models\User;
use App\Payments\CheckoutService;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployer(): User
    {
        $user = User::factory()->create(['role' => UserRole::Employer]);
        Company::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    #[Test]
    public function addon_checkout_page_renders_for_hot(): void
    {
        $employer = $this->makeEmployer();
        $this->actingAs($employer);

        $this->get(route('employer.billing.checkout.addon', ['addon' => 'hot']))
            ->assertOk()
            ->assertSee('199')
            ->assertSee('HOT');
    }

    #[Test]
    public function addon_checkout_page_renders_for_top(): void
    {
        $employer = $this->makeEmployer();
        $this->actingAs($employer);

        $this->get(route('employer.billing.checkout.addon', ['addon' => 'top']))
            ->assertOk()
            ->assertSee('299')
            ->assertSee('TOP');
    }

    #[Test]
    public function addon_checkout_page_renders_for_cv_access(): void
    {
        $employer = $this->makeEmployer();
        $this->actingAs($employer);

        $this->get(route('employer.billing.checkout.addon', ['addon' => 'cv_access']))
            ->assertOk()
            ->assertSee('990');
    }

    #[Test]
    public function invalid_addon_type_returns_404(): void
    {
        $employer = $this->makeEmployer();
        $this->actingAs($employer);

        $this->get(route('employer.billing.checkout.addon', ['addon' => 'unknown']))
            ->assertNotFound();
    }

    #[Test]
    public function guest_cannot_access_addon_checkout(): void
    {
        $this->get(route('employer.billing.checkout.addon', ['addon' => 'hot']))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function seeker_cannot_access_addon_checkout(): void
    {
        $seeker = User::factory()->create(['role' => UserRole::Candidate]);
        $this->actingAs($seeker);

        $this->get(route('employer.billing.checkout.addon', ['addon' => 'hot']))
            ->assertForbidden();
    }

    #[Test]
    public function checkout_service_creates_addon_checkout_url(): void
    {
        $employer = $this->makeEmployer();

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('name')->willReturn('liqpay');
        $gateway->method('createCheckout')->willReturnCallback(
            fn(CheckoutData $data) => 'https://pay.example.com/' . $data->orderId
        );

        $service = new CheckoutService($gateway);
        $url = $service->createAddonCheckout(AddonType::Hot, $employer);

        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('https://', $url);
    }

    #[Test]
    public function build_anonymous_order_id_has_correct_prefix(): void
    {
        $orderId = CheckoutService::buildAnonymousOrderId(42);
        $this->assertStringStartsWith('anon_42_', $orderId);
    }

    #[Test]
    public function parse_anonymous_order_id_returns_vacancy_id(): void
    {
        $orderId = CheckoutService::buildAnonymousOrderId(42);
        $this->assertSame(42, CheckoutService::parseAnonymousOrderId($orderId));
    }

    #[Test]
    public function parse_anonymous_order_id_returns_null_for_other_prefixes(): void
    {
        $this->assertNull(CheckoutService::parseAnonymousOrderId('vac_42_30_abc123'));
        $this->assertNull(CheckoutService::parseAnonymousOrderId('sub_1_2_abc'));
    }

    #[Test]
    public function checkout_service_creates_anonymous_publication_checkout(): void
    {
        $employer = $this->makeEmployer();
        $vacancy  = \App\Models\Vacancy::factory()->create([
            'company_id' => $employer->company->id,
            'title'      => 'PHP Developer',
        ]);

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('name')->willReturn('mono');
        $gateway->method('createCheckout')->willReturnCallback(
            fn(CheckoutData $data) => 'https://pay.example.com/' . $data->orderId
        );

        $service = new CheckoutService($gateway);
        $url = $service->createAnonymousPublicationCheckout($vacancy, $employer);

        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('anon_', $url);
    }
}
