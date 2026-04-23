<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_project_exposes_postgresql_connections_even_if_test_runtime_overrides_default_connection(): void
    {
        $this->assertContains(config('database.default'), ['pgsql', 'sqlite']);
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame('pgsql', config('database.connections.telegram_runtime.driver'));
        $this->assertSame('pgsql', config('database.connections.posts_source.driver'));
    }

    public function test_posts_source_config_matches_prod_db_contract(): void
    {
        $this->assertSame('posts_source', config('posts.source.connection'));
        $this->assertSame('public.posts', config('posts.source.qualified_table'));
        $this->assertSame('content', config('posts.source.content_column'));
        $this->assertSame('media_url', config('posts.source.media_url_column'));
        $this->assertSame('published_at', config('posts.source.published_at_column'));
        $this->assertSame(1, config('posts.scheduler.max_posts_per_run'));
        $this->assertSame('00:00', config('posts.scheduler.quiet_hours_start_utc'));
        $this->assertSame('06:00', config('posts.scheduler.quiet_hours_end_utc'));
    }
}
