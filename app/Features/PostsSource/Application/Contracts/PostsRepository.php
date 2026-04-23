<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Contracts;

use App\Features\PostsSource\Domain\Data\SourcePost;

interface PostsRepository
{
    /**
     * @return list<SourcePost>
     */
    public function findUnpublished(int $limit = 10): array;

    public function findById(int $id): ?SourcePost;

    public function countUnpublished(): int;

    /**
     * @param  list<int>  $excludeSourceIds
     */
    public function pickUnpublished(bool $requireMedia = false, array $excludeSourceIds = []): ?SourcePost;

    public function markPublished(int $id): void;
}
