<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class CallbackDataPayload
{
    public function __construct(
        public readonly string  $action,
        public readonly string  $resourceType,
        public readonly int     $resourceId,
        public readonly ?string $param = null,
    ) {}
}
