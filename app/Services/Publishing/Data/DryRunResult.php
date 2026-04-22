<?php

declare(strict_types=1);

namespace App\Services\Publishing\Data;

final class DryRunResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly string $mode,
        public readonly string $cleanedText,
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}
}
