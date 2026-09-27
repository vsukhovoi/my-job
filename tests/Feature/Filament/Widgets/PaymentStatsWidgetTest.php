<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\PaymentStatsWidget;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        config(['payments.prices' => [15 => 10000, 30 => 20000, 90 => 50000]]);
        \Carbon\Carbon::setTestNow('2025-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renders_without_error(): void
    {
        Livewire::test(PaymentStatsWidget::class)
            ->assertOk();
    }

    public function test_today_count_shown(): void
    {
        $vacancy = Vacancy::factory()->active()->create();
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->today()->count(2)->create();

        Livewire::test(PaymentStatsWidget::class)
            ->assertSee('2');
    }

    public function test_this_month_revenue_computed_from_config(): void
    {
        $vacancy = Vacancy::factory()->active()->create();
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->thisMonth()->count(1)->create();

        $component = Livewire::test(PaymentStatsWidget::class);

        $expectedUah = 20000 / 100;
        $component->assertSee((string) number_format($expectedUah, 0, '.', ' '));
    }

    public function test_this_month_revenue_includes_stored_subscription_amount(): void
    {
        PaymentTransaction::factory()->forSubscription(1, 2, 49900)->thisMonth()->create();
        PaymentTransaction::factory()->forSubscription(1, 2, 49900)->thisMonth()->create();

        Livewire::test(PaymentStatsWidget::class)
            ->assertSee('998 ₴');
    }

    public function test_last_month_transactions_not_counted_as_today(): void
    {
        $vacancy = Vacancy::factory()->active()->create();
        PaymentTransaction::factory()->forVacancy($vacancy, 30)->lastMonth()->count(3)->create();

        $todayCount = PaymentTransaction::whereDate('processed_at', today())->count();
        $this->assertSame(0, $todayCount);
    }

    public function test_empty_state_renders_without_error(): void
    {
        Livewire::test(PaymentStatsWidget::class)
            ->assertOk();
    }
}
