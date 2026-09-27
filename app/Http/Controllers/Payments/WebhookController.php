<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Events\VacancyExtended;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vacancy;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\DuplicatePaymentException;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * POST /webhooks/payments/{gateway}
     */
    public function handle(Request $request, string $gateway): Response
    {
        $gw = $this->registry->get($gateway);

        if (! $gw) {
            Log::channel('payments')->warning("Unknown payment gateway: {$gateway}", ['ip' => $request->ip()]);
            return response('', 404);
        }

        try {
            $result = $gw->parseWebhook($request);
        } catch (InvalidWebhookSignatureException $e) {
            Log::channel('payments')->warning("Invalid signature [{$gateway}]", [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response('', 400);
        } catch (\Throwable $e) {
            Log::channel('payments')->error("Webhook parse failed [{$gateway}]", ['error' => $e->getMessage()]);
            report($e);
            return $gw->successResponse();
        }

        if (! $result->isPaid) {
            Log::channel('payments')->info("Not paid [{$gateway}]", [
                'event_id' => $result->externalEventId,
                'reason'   => $result->failureReason,
            ]);
            return $gw->successResponse();
        }

        try {
            $this->checkIdempotency($result->externalEventId, $gateway);
        } catch (DuplicatePaymentException) {
            Log::channel('payments')->info("Duplicate event ignored [{$gateway}]", [
                'event_id' => $result->externalEventId,
            ]);
            return $gw->successResponse();
        }

        try {
            if ($result->isAnonymousPublication()) {
                $this->processAnonymousActivation($result, $gateway);
            } elseif ($result->isPlanSubscription()) {
                $this->processPlanSubscription($result, $gateway);
            } else {
                $this->processExtension($result, $gateway);
            }
        } catch (\Throwable $e) {
            Log::channel('payments')->error("Payment processing failed [{$gateway}]", [
                'event_id' => $result->externalEventId,
                'error'    => $e->getMessage(),
            ]);
            report($e);
            return $gw->successResponse();
        }

        $this->markProcessed($result->externalEventId, $gateway, $result->orderId, $result->amountKopecks);

        return $gw->successResponse();
    }

    private function processPlanSubscription(PaymentResult $result, string $gateway): void
    {
        if (! $result->planId || ! $result->userId) {
            throw new \UnexpectedValueException(
                "Cannot extract plan_id or user_id from orderId={$result->orderId}"
            );
        }

        DB::transaction(function () use ($result, $gateway): void {
            $user = User::lockForUpdate()->find($result->userId);
            $plan = SubscriptionPlan::find($result->planId);

            if (! $user || ! $plan) {
                throw new \DomainException("User {$result->userId} or plan {$result->planId} not found");
            }

            $this->subscriptionService->activate($user, $plan);

            Log::channel('payments')->info("Plan subscription activated [{$gateway}]", [
                'user_id'  => $user->id,
                'plan_id'  => $plan->id,
                'plan'     => $plan->name,
                'event_id' => $result->externalEventId,
            ]);
        });
    }

    private function processAnonymousActivation(PaymentResult $result, string $gateway): void
    {
        if (! $result->anonymousVacancyId) {
            throw new \UnexpectedValueException(
                "Cannot extract vacancy_id from orderId={$result->orderId}"
            );
        }

        DB::transaction(function () use ($result, $gateway): void {
            $vacancy = Vacancy::lockForUpdate()->find($result->anonymousVacancyId);

            if (! $vacancy) {
                throw new \DomainException("Vacancy {$result->anonymousVacancyId} not found");
            }

            $vacancy->update(['is_active' => true]);

            Log::channel('payments')->info("Anonymous vacancy activated [{$gateway}]", [
                'vacancy_id' => $vacancy->id,
                'event_id'   => $result->externalEventId,
            ]);
        });
    }

    private function checkIdempotency(string $eventId, string $gateway): void
    {
        $exists = DB::table('payment_processed_events')
            ->where('event_id', $eventId)
            ->where('gateway', $gateway)
            ->exists();

        if ($exists) {
            throw new DuplicatePaymentException($eventId);
        }
    }

    private function processExtension(PaymentResult $result, string $gateway): void
    {
        if (! $result->vacancyId || ! $result->days) {
            throw new \UnexpectedValueException(
                "Cannot extract vacancy_id or days from orderId={$result->orderId}"
            );
        }

        DB::transaction(function () use ($result, $gateway): void {
            $vacancy = Vacancy::lockForUpdate()->find((int) $result->vacancyId);

            if (! $vacancy) {
                throw new \DomainException("Vacancy {$result->vacancyId} not found");
            }

            $vacancy->extend($result->days);

            VacancyExtended::dispatch(
                vacancy:       $vacancy,
                days:          $result->days,
                amountCents:   $result->amountKopecks,
                currency:      $result->currency,
                stripeEventId: $result->externalEventId,
            );

            Log::channel('payments')->info("Vacancy extended [{$gateway}]", [
                'vacancy_id' => $vacancy->id,
                'days'       => $result->days,
                'event_id'   => $result->externalEventId,
            ]);
        });
    }

    private function markProcessed(string $eventId, string $gateway, string $orderId, int $amountKopecks): void
    {
        DB::table('payment_processed_events')->insert([
            'event_id'       => $eventId,
            'gateway'        => $gateway,
            'order_id'       => $orderId,
            'amount_kopecks' => $amountKopecks ?: null,
            'processed_at'   => now(),
        ]);
    }
}
