<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class TelegramMessageResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?int    $messageId = null,
        public readonly ?int    $chatId    = null,
        public readonly ?string $error     = null,
    ) {}

    public static function ok(int $messageId, int $chatId): self
    {
        return new self(success: true, messageId: $messageId, chatId: $chatId);
    }

    public static function fail(string $error): self
    {
        return new self(success: false, error: $error);
    }
}
