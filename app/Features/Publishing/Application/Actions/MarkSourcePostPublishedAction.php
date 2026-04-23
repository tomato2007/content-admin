<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Actions;

use App\Features\PostsSource\Application\Contracts\PostsRepository;
use App\Models\PlannedPost;

final class MarkSourcePostPublishedAction
{
    public function __construct(
        private readonly PostsRepository $postsRepository,
    ) {}

    public function execute(PlannedPost $plannedPost): void
    {
        if ($plannedPost->source_type !== 'posts_source' || $plannedPost->source_id === null) {
            return;
        }

        $sourceId = (int) $plannedPost->source_id;

        if ($sourceId <= 0) {
            return;
        }

        $this->postsRepository->markPublished($sourceId);
    }
}
