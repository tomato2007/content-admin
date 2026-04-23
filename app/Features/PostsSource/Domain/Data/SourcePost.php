<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Domain\Data;

use Carbon\CarbonImmutable;

final class SourcePost
{
    public function __construct(
        public readonly int $id,
        public readonly string $content,
        public readonly ?string $mediaUrl,
        public readonly ?CarbonImmutable $publishedAt,
    ) {}
}
