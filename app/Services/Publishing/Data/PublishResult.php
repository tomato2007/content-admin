<?php

declare(strict_types=1);

namespace App\Services\Publishing\Data;

use Carbon\CarbonImmutable;

final class PublishResult
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?array $payload = null,
        public readonly ?array $response = null,
        public readonly ?string $error = null,
        public readonly ?CarbonImmutable $sentAt = null,
        public readonly string $mode = 'text',
        public readonly ?string $externalReference = null,
    ) {}
}
