<?php

declare(strict_types=1);

namespace App\Services\Publishing\Data;

use App\Models\PlannedPost;
use App\Models\PlatformAccount;

final class PublishRequest
{
    public function __construct(
        public readonly PlannedPost $plannedPost,
        public readonly PlatformAccount $platformAccount,
        public readonly bool $force = false,
        public readonly ?int $triggeredByUserId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly string $attemptType = 'manual',
    ) {}
}
