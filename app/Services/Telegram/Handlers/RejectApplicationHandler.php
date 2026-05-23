<?php

declare(strict_types=1);

namespace App\Services\Telegram\Handlers;

use App\DataTransferObjects\CallbackDataPayload;
use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Models\User;

final class RejectApplicationHandler extends AbstractApplicationCallbackHandler
{
    public function canHandle(CallbackDataPayload $payload): bool
    {
        return $payload->action === 'reject' && $payload->resourceType === 'application';
    }

    public function handle(CallbackDataPayload $payload, User $user, int $messageId): void
    {
        $application = $this->getApplication($payload->resourceId);

        if ($application === null) {
            return;
        }

        if (! $this->ensureEmployerOwnsApplication($application, $user)) {
            return;
        }

        if ($application->status === ApplicationStatus::Rejected) {
            return;
        }

        $oldStatus = $application->status;
        $application->update(['status' => ApplicationStatus::Rejected]);

        ApplicationStatusChanged::dispatch(
            $application->fresh(['vacancy.company', 'user']),
            $oldStatus,
            ApplicationStatus::Rejected,
            $user,
        );

        $datetime = now()->format('d.m.Y о H:i');
        $this->clearKeyboardWithStatus(
            (int) $user->telegram_id,
            $messageId,
            $application,
            "❌ Відхилено о {$datetime}",
        );
    }
}
