<?php

declare(strict_types=1);

namespace App\Payments;

use App\Enums\AddonType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vacancy;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;

class CheckoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function createVacancyExtensionCheckout(
        Vacancy $vacancy,
        int $days,
        ?string $successUrl = null,
        ?string $cancelUrl  = null,
    ): string {
        $prices        = config('payments.prices');
        $amountKopecks = $prices[$days] ?? throw new \InvalidArgumentException("Unknown plan: {$days} days");

        $orderId = self::buildVacancyOrderId($vacancy->id, $days);

        $data = new CheckoutData(
            amountKopecks: $amountKopecks,
            currency:      'UAH',
            orderId:       $orderId,
            description:   "Публікація вакансії «{$vacancy->title}» на {$days} днів",
            successUrl:    $successUrl ?? route('employer.vacancies.payment.success', $vacancy),
            cancelUrl:     $cancelUrl  ?? route('employer.vacancies.payment.cancel', $vacancy),
            webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
            vacancy:       $vacancy,
            days:          $days,
            customerEmail: $vacancy->company?->user?->email,
            customerName:  $vacancy->company?->name,
        );

        return $this->gateway->createCheckout($data);
    }

    public function createPlanSubscriptionCheckout(User $user, SubscriptionPlan $plan): string
    {
        $orderId = self::buildSubscriptionOrderId($user->id, $plan->id);

        $data = new CheckoutData(
            amountKopecks: $plan->price_monthly * 100,
            currency:      'UAH',
            orderId:       $orderId,
            description:   "Тариф «{$plan->name}» — 1 місяць",
            successUrl:    route('employer.billing.success'),
            cancelUrl:     route('employer.billing'),
            webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
            planId:        $plan->id,
            userId:        $user->id,
            customerEmail: $user->email,
            customerName:  $user->company?->name,
        );

        return $this->gateway->createCheckout($data);
    }

    public function createAddonCheckout(AddonType $addon, User $employer): string
    {
        $orderId = self::buildAddonOrderId($addon, $employer->id);

        $data = new CheckoutData(
            amountKopecks: $addon->price() * 100,
            currency:      'UAH',
            orderId:       $orderId,
            description:   $addon->label() . " — {$addon->durationDays()} днів",
            successUrl:    route('employer.billing.success'),
            cancelUrl:     route('employer.billing'),
            webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
            userId:        $employer->id,
            customerEmail: $employer->email,
            customerName:  $employer->company?->name,
        );

        return $this->gateway->createCheckout($data);
    }

    // ── Order ID helpers ──────────────────────────────────────────────────────

    /**
     * Format: vac_{vacancyId}_{days}_{suffix}
     */
    public static function buildVacancyOrderId(int $vacancyId, int $days): string
    {
        return sprintf('vac_%d_%d_%s', $vacancyId, $days, substr(uniqid(), -6));
    }

    /** @deprecated Use buildVacancyOrderId */
    public static function buildOrderId(int $vacancyId, int $days): string
    {
        return self::buildVacancyOrderId($vacancyId, $days);
    }

    /**
     * Format: addon_{addonValue}_{userId}_{timestamp}
     */
    public static function buildAddonOrderId(AddonType $addon, int $userId): string
    {
        return sprintf('addon_%s_%d_%d', $addon->value, $userId, time());
    }

    /**
     * Format: sub_{userId}_{planId}_{suffix}
     */
    public static function buildSubscriptionOrderId(int $userId, int $planId): string
    {
        return sprintf('sub_%d_%d_%s', $userId, $planId, substr(uniqid(), -6));
    }

    /** @return array{0: int|null, 1: int|null} [vacancyId, days] */
    public static function parseOrderId(string $orderId): array
    {
        if (preg_match('/^vac_(\d+)_(\d+)_/', $orderId, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        return [null, null];
    }

    /** @return array{0: int|null, 1: int|null} [userId, planId] */
    public static function parseSubscriptionOrderId(string $orderId): array
    {
        if (preg_match('/^sub_(\d+)_(\d+)_/', $orderId, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        return [null, null];
    }

    // ── Anonymous publication helpers ─────────────────────────────────────────

    /**
     * Create a checkout URL for anonymous vacancy publication.
     */
    public function createAnonymousPublicationCheckout(Vacancy $vacancy, User $user): string
    {
        $orderId = self::buildAnonymousOrderId($vacancy->id);

        $data = new CheckoutData(
            amountKopecks: AddonType::AnonymousPublication->price() * 100,
            currency:      'UAH',
            orderId:       $orderId,
            description:   "Анонімна публікація вакансії «{$vacancy->title}»",
            successUrl:    route('employer.billing'),
            cancelUrl:     route('employer.billing'),
            webhookUrl:    route('webhooks.payments', ['gateway' => $this->gateway->name()]),
            vacancy:       $vacancy,
            userId:        $user->id,
        );

        return $this->gateway->createCheckout($data);
    }

    /**
     * Format: anon_{vacancyId}_{suffix}
     */
    public static function buildAnonymousOrderId(int $vacancyId): string
    {
        return sprintf('anon_%d_%s', $vacancyId, substr(uniqid(), -6));
    }

    /**
     * Parse anonymous order ID and return vacancy ID, or null if not an anon order.
     */
    public static function parseAnonymousOrderId(string $orderId): ?int
    {
        if (preg_match('/^anon_(\d+)_/', $orderId, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
