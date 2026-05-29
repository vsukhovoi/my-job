<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

final readonly class PaymentResult
{
    public function __construct(
        public bool $isPaid,
        public string $gatewayName,
        public string $externalEventId,
        public string $orderId,
        public int $amountKopecks,
        public string $currency,
        public ?string $vacancyId,
        public ?int $days,
        public ?int $planId = null,
        public ?int $userId = null,
        public ?string $failureReason = null,
        public ?int $anonymousVacancyId = null,
    ) {}

    public function isPlanSubscription(): bool
    {
        return $this->planId !== null && $this->userId !== null;
    }

    public function isAnonymousPublication(): bool
    {
        return $this->anonymousVacancyId !== null;
    }
}
