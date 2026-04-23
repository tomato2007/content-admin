<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Infrastructure\Config;

use InvalidArgumentException;

final class PostsSourceConfig
{
    public function __construct(
        public readonly string $connection,
        public readonly string $qualifiedTable,
        public readonly string $schema,
        public readonly string $table,
        public readonly string $contentColumn,
        public readonly string $mediaUrlColumn,
        public readonly string $publishedAtColumn,
    ) {
        $this->assertIdentifier($this->connection, 'connection');
        $this->assertQualifiedTable($this->qualifiedTable);
        $this->assertIdentifier($this->schema, 'schema');
        $this->assertIdentifier($this->table, 'table');
        $this->assertIdentifier($this->contentColumn, 'content column');
        $this->assertIdentifier($this->mediaUrlColumn, 'media_url column');
        $this->assertIdentifier($this->publishedAtColumn, 'published_at column');
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            connection: (string) ($config['connection'] ?? 'posts_source'),
            qualifiedTable: (string) ($config['qualified_table'] ?? 'public.posts'),
            schema: (string) ($config['schema'] ?? 'public'),
            table: (string) ($config['table'] ?? 'posts'),
            contentColumn: (string) ($config['content_column'] ?? 'content'),
            mediaUrlColumn: (string) ($config['media_url_column'] ?? 'media_url'),
            publishedAtColumn: (string) ($config['published_at_column'] ?? 'published_at'),
        );
    }

    public function tableReference(?string $driver = null): string
    {
        if ($driver === 'sqlite') {
            return $this->table;
        }

        return sprintf('%s.%s', $this->quoteIdentifier($this->schema), $this->quoteIdentifier($this->table));
    }

    public function qualifiedTableMatchesSchemaTable(): bool
    {
        return $this->qualifiedTable === sprintf('%s.%s', $this->schema, $this->table);
    }

    private function assertQualifiedTable(string $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException('Posts source qualified_table must be in schema.table form.');
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException(sprintf('Posts source %s contains an invalid SQL identifier.', $label));
        }
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
