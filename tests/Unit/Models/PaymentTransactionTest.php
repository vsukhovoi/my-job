<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\Carbon::setTestNow('2025-06-15 12:00:00');
        config(['payments.prices' => [15 => 10000, 30 => 20000, 90 => 50000]]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vacancy_id_decoded_from_order_id(): void
    {
        $vacancy = Vacancy::factory()->active()->create();
        $tx = PaymentTransaction::factory()->forVacancy($vacancy, 30)->create();

        $this->assertSame($vacancy->id, $tx->vacancy_id);
    }

    public function test_days_decoded_from_order_id(): void
    {
        $vacancy = Vacancy::factory()->active()->create();

        foreach ([15, 30, 90] as $days) {
            $tx = PaymentTransaction::factory()->forVacancy($vacancy, $days)->create();
            $this->assertSame($days, $tx->days);
        }
    }

    public function test_amount_uah_computed_from_config(): void
    {
        $vacancy = Vacancy::factory()->active()->create();

        $tx30 = PaymentTransaction::factory()->forVacancy($vacancy, 30)->create();
        $tx90 = PaymentTransaction::factory()->forVacancy($vacancy, 90)->create();

        $this->assertSame(200.0, $tx30->amount_uah);
        $this->assertSame(500.0, $tx90->amount_uah);
    }

    public function test_amount_uah_null_if_order_id_unrecognized(): void
    {
        $tx = PaymentTransaction::factory()->create(['order_id' => 'unknown_format_123']);

        $this->assertNull($tx->amount_uah);
    }

    public function test_amount_uah_prefers_stored_amount(): void
    {
        $vacancy = Vacancy::factory()->active()->create();
        $tx = PaymentTransaction::factory()->forVacancy($vacancy, 30)->create(['amount_kopecks' => 18050]);

        $this->assertSame(180.5, $tx->fresh()->amount_uah);
    }

    public function test_subscription_amount_and_purpose(): void
    {
        $plan = SubscriptionPlan::create([
            'type'          => 'start',
            'name'          => 'Старт',
            'price_monthly' => 499,
            'features'      => [],
        ]);
        $tx = PaymentTransaction::factory()->forSubscription(107, $plan->id, 49900)->create();

        $this->assertSame(499.0, $tx->fresh()->amount_uah);
        $this->assertSame('Тариф «Старт», 1 міс.', $tx->purpose);
    }

    public function test_purpose_for_other_order_formats(): void
    {
        $vacancy = Vacancy::factory()->active()->create();

        $cases = [
            "vac_{$vacancy->id}_30_abc123"            => "Вакансія #{$vacancy->id}, 30 дн.",
            'anon_42_abc123'                          => 'Анонімна публікація, вакансія #42',
            'addon_cv_access_7_1790000000'            => 'Доступ до бази CV',
            'sub_7_999_abc123'                        => 'Тариф #999, 1 міс.',
            'unknown_format_123'                      => '—',
        ];

        foreach ($cases as $orderId => $expected) {
            $tx = new PaymentTransaction(['gateway' => 'mono', 'order_id' => $orderId]);
            $this->assertSame($expected, $tx->purpose, $orderId);
        }
    }

    public function test_gateway_label_returns_correct_names(): void
    {
        $cases = [
            'mono'      => 'MonoPay',
            'wayforpay' => 'WayForPay',
            'liqpay'    => 'LiqPay',
            'stripe'    => 'Stripe',
            'unknown'   => 'Unknown',
        ];

        foreach ($cases as $gateway => $expected) {
            $tx = new PaymentTransaction(['gateway' => $gateway, 'order_id' => 'vac_1_30_abc']);
            $this->assertSame($expected, $tx->gateway_label);
        }
    }

    public function test_model_prevents_creation_via_eloquent(): void
    {
        $result = PaymentTransaction::create([
            'event_id'     => 'test_evt',
            'gateway'      => 'mono',
            'order_id'     => 'vac_1_30_abc',
            'processed_at' => now(),
        ]);

        $this->assertFalse($result->exists);
        $this->assertDatabaseMissing('payment_processed_events', ['event_id' => 'test_evt']);
    }

    public function test_model_prevents_update_via_eloquent(): void
    {
        $tx = PaymentTransaction::factory()->mono()->create();
        $original = $tx->gateway;

        $result = $tx->update(['gateway' => 'stripe']);

        $this->assertFalsy($result);
        $this->assertSame($original, $tx->fresh()->gateway);
    }

    public function test_model_prevents_delete_via_eloquent(): void
    {
        $tx = PaymentTransaction::factory()->create();
        $eventId = $tx->event_id;

        $tx->delete();

        $this->assertDatabaseHas('payment_processed_events', ['event_id' => $eventId]);
    }

    private function assertFalsy(mixed $value): void
    {
        $this->assertFalse((bool) $value);
    }
}
