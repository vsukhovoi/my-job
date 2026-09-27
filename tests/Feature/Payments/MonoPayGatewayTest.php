<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Events\VacancyExtended;
use App\Enums\UserRole;
use App\Enums\VacancyStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Payments\CheckoutService;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

class MonoPayGatewayTest extends PaymentTestCase
{
    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        // MonoPay підписує webhook ECDSA (prime256v1 + SHA-256);
        // /api/merchant/pubkey віддає base64-кодований PEM
        $this->privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        $publicKeyB64 = base64_encode(openssl_pkey_get_details($this->privateKey)['key']);

        config([
            'payments.gateways.mono.token'      => 'test_mono_token',
            'payments.gateways.mono.public_key' => $publicKeyB64,
        ]);
    }

    public function test_webhook_missing_sign_header_returns_400(): void
    {
        // Відсутній X-Sign header → InvalidWebhookSignatureException → 400
        $this->postMono(json_encode(['invoiceId' => 'x']), null)
            ->assertStatus(400);
    }

    public function test_webhook_invalid_signature_returns_400(): void
    {
        // 'invalid!!' — не є валідним base64 → decode повертає false → 400
        $body = json_encode(['invoiceId' => 'x', 'status' => 'success']);
        $this->postMono($body, 'invalid_base64!!')->assertStatus(400);
    }

    public function test_not_paid_status_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $body    = json_encode([
            'invoiceId' => 'inv_fail_001',
            'status'    => 'failure',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);

        $this->postMono($body, $this->sign($body))->assertOk();

        Event::assertNotDispatched(VacancyExtended::class);
        $this->assertSame(VacancyStatus::Active, $vacancy->fresh()->status);
    }

    public function test_successful_webhook_extends_vacancy(): void
    {
        Event::fake();

        $vacancy    = $this->makeVacancy();
        $orderId    = $this->buildOrderId($vacancy, 30);
        $oldExpires = $vacancy->expires_at->copy();

        $body = json_encode([
            'invoiceId' => 'inv_success_001',
            'status'    => 'success',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);

        $this->postMono($body, $this->sign($body))->assertOk();

        $this->assertTrue($vacancy->fresh()->expires_at->gt($oldExpires));
        Event::assertDispatched(VacancyExtended::class, fn ($e) =>
            $e->vacancy->id === $vacancy->id && $e->days === 30
        );
    }

    public function test_duplicate_invoice_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $body    = json_encode([
            'invoiceId' => 'inv_idempotency_001',
            'status'    => 'success',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);
        $sign = $this->sign($body);

        $this->postMono($body, $sign)->assertOk();
        $firstExpires = $vacancy->fresh()->expires_at->copy();

        $this->postMono($body, $sign)->assertOk();

        $this->assertSame(
            $firstExpires->toIso8601String(),
            $vacancy->fresh()->expires_at->toIso8601String(),
        );
        Event::assertDispatchedTimes(VacancyExtended::class, 1);
    }

    public function test_signature_from_other_key_returns_400(): void
    {
        $body     = json_encode(['invoiceId' => 'x', 'status' => 'success']);
        $otherKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_sign($body, $signature, $otherKey, OPENSSL_ALGO_SHA256);

        $this->postMono($body, base64_encode($signature))->assertStatus(400);
    }

    public function test_successful_subscription_webhook_activates_plan(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $plan     = SubscriptionPlan::create([
            'type'          => 'start',
            'name'          => 'Старт',
            'price_monthly' => 499,
            'features'      => ['active_jobs' => 3],
        ]);

        $orderId = CheckoutService::buildSubscriptionOrderId($employer->id, $plan->id);
        $body    = json_encode([
            'invoiceId' => 'inv_sub_001',
            'status'    => 'success',
            'reference' => $orderId,
            'amount'    => 49900,
        ]);

        $this->postMono($body, $this->sign($body))->assertOk();

        $this->assertDatabaseHas('employer_subscriptions', [
            'user_id' => $employer->id,
            'plan_id' => $plan->id,
            'status'  => 'active',
        ]);
        $this->assertDatabaseHas('payment_processed_events', [
            'event_id'       => 'inv_sub_001',
            'gateway'        => 'mono',
            'order_id'       => $orderId,
            'amount_kopecks' => 49900,
        ]);
    }

    // =========================================================================

    /**
     * Надсилає raw JSON body з X-Sign header — точно як MonoPay надсилає webhook.
     * $sign === null означає відсутній заголовок (тест missing header).
     */
    private function postMono(string $body, ?string $sign): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($sign !== null) {
            $server['HTTP_X_SIGN'] = $sign;
        }

        return $this->call('POST', '/webhooks/payments/mono', [], [], [], $server, $body);
    }

    private function sign(string $body): string
    {
        openssl_sign($body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
