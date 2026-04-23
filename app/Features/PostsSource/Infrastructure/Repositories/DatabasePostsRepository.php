<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Infrastructure\Repositories;

use App\Features\PostsSource\Application\Contracts\PostsRepository;
use App\Features\PostsSource\Domain\Data\SourcePost;
use App\Features\PostsSource\Infrastructure\Config\PostsSourceConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionResolverInterface;

final class DatabasePostsRepository implements PostsRepository
{
    public function __construct(
        private readonly ConnectionResolverInterface $db,
        private readonly PostsSourceConfig $config,
    ) {}

    public function findUnpublished(int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->baseQuery()
            ->whereNull($this->config->publishedAtColumn)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): SourcePost => $this->mapRow($row))
            ->all();
    }

    public function findById(int $id): ?SourcePost
    {
        $row = $this->baseQuery()
            ->where('id', $id)
            ->first();

        return $row === null ? null : $this->mapRow($row);
    }

    public function countUnpublished(): int
    {
        return $this->baseQuery()
            ->whereNull($this->config->publishedAtColumn)
            ->count();
    }

    public function pickUnpublished(bool $requireMedia = false, array $excludeSourceIds = []): ?SourcePost
    {
        $connection = $this->db->connection($this->config->connection);
        $query = $connection
            ->table($this->config->tableReference($connection->getDriverName()))
            ->select([
                'id',
                $this->config->contentColumn,
                $this->config->mediaUrlColumn,
                $this->config->publishedAtColumn,
            ])
            ->whereNull($this->config->publishedAtColumn)
            ->orderBy('id');

        if ($requireMedia) {
            $query->whereNotNull($this->config->mediaUrlColumn);
        }

        if ($excludeSourceIds !== []) {
            $query->whereNotIn('id', $excludeSourceIds);
        }

        if ($connection->getDriverName() === 'pgsql') {
            $query->lock('FOR UPDATE SKIP LOCKED');
        }

        $row = $query->first();

        return $row === null ? null : $this->mapRow($row);
    }

    private function baseQuery()
    {
        $connection = $this->db->connection($this->config->connection);

        return $connection
            ->table($this->config->tableReference($connection->getDriverName()))
            ->select([
                'id',
                $this->config->contentColumn,
                $this->config->mediaUrlColumn,
                $this->config->publishedAtColumn,
            ]);
    }

    private function mapRow(object $row): SourcePost
    {
        $contentColumn = $this->config->contentColumn;
        $mediaUrlColumn = $this->config->mediaUrlColumn;
        $publishedAtColumn = $this->config->publishedAtColumn;
        $publishedAt = $row->{$publishedAtColumn};

        return new SourcePost(
            id: (int) $row->id,
            content: (string) $row->{$contentColumn},
            mediaUrl: $row->{$mediaUrlColumn} !== null ? (string) $row->{$mediaUrlColumn} : null,
            publishedAt: $publishedAt !== null ? CarbonImmutable::parse((string) $publishedAt) : null,
        );
    }
}
