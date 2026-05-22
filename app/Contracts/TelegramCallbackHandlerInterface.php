<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\CallbackDataPayload;
use App\Models\User;

interface TelegramCallbackHandlerInterface
{
    public function canHandle(CallbackDataPayload $payload): bool;

    public function handle(CallbackDataPayload $payload, User $user, int $messageId): void;
}
