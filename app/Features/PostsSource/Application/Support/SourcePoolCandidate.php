<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Support;

use App\Features\PostsSource\Domain\Data\SourcePost;

final class SourcePoolCandidate
{
    public function __construct(
        public readonly SourcePost $sourcePost,
        public readonly string $cleanedContent,
        public readonly bool $hasMedia,
    ) {}
}
