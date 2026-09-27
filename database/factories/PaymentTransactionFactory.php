<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\Vacancy;
use App\Payments\CheckoutService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /** Bypasses Eloquent lifecycle guards via direct DB insert. */
    protected function store(Collection $results): void
    {
        $results->each(function (Model $model): void {
            $attrs = $model->getAttributes();

            DB::table('payment_processed_events')->insert([
                'event_id'     => $attrs['event_id'],
                'gateway'      => $attrs['gateway'],
                'order_id'       => $attrs['order_id'],
                'amount_kopecks' => $attrs['amount_kopecks'] ?? null,
                'processed_at'   => $attrs['processed_at'] instanceof \DateTimeInterface
                    ? $attrs['processed_at']->format('Y-m-d H:i:s')
                    : (string) $attrs['processed_at'],
            ]);

            $model->exists            = true;
            $model->wasRecentlyCreated = true;
        });
    }

    public function definition(): array
    {
        $gateway   = $this->faker->randomElement(['mono', 'wayforpay', 'liqpay', 'stripe']);
        $days      = $this->faker->randomElement([15, 30, 90]);
        $vacancyId = 1;

        return [
            'event_id'     => $gateway . '_' . $this->faker->unique()->uuid(),
            'gateway'      => $gateway,
            'order_id'     => CheckoutService::buildOrderId($vacancyId, $days),
            'processed_at' => now(),
        ];
    }

    public function mono(): static
    {
        return $this->state(['gateway' => 'mono']);
    }

    public function liqpay(): static
    {
        return $this->state(['gateway' => 'liqpay']);
    }

    public function wayforpay(): static
    {
        return $this->state(['gateway' => 'wayforpay']);
    }

    public function stripe(): static
    {
        return $this->state(['gateway' => 'stripe']);
    }

    public function forVacancy(Vacancy $vacancy, int $days = 30): static
    {
        return $this->state([
            'order_id' => CheckoutService::buildOrderId($vacancy->id, $days),
        ]);
    }

    public function forSubscription(int $userId, int $planId, int $amountKopecks): static
    {
        return $this->state([
            'order_id'       => CheckoutService::buildSubscriptionOrderId($userId, $planId),
            'amount_kopecks' => $amountKopecks,
        ]);
    }

    public function daysAgo(int $days): static
    {
        return $this->state(['processed_at' => now()->subDays($days)]);
    }

    public function today(): static
    {
        return $this->state(['processed_at' => now()]);
    }

    public function thisMonth(): static
    {
        return $this->state(['processed_at' => now()->startOfMonth()->addDays(5)]);
    }

    public function lastMonth(): static
    {
        return $this->state([
            'processed_at' => now()->subMonth()->startOfMonth()->addDays(5),
        ]);
    }
}
