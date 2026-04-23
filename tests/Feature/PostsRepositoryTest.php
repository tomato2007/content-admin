<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\PostsSource\Application\Contracts\PostsRepository;
use App\Features\PostsSource\Infrastructure\Config\PostsSourceConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PostsRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.posts_source', config('database.connections.'.config('database.default')));
        config()->set('posts.source.connection', 'posts_source');
        config()->set('posts.source.schema', 'main');
        config()->set('posts.source.table', 'posts');
        config()->set('posts.source.qualified_table', 'main.posts');

        Schema::connection('posts_source')->dropIfExists('posts');
        Schema::connection('posts_source')->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->text('content')->nullable();
            $table->string('media_url')->nullable();
            $table->timestamp('published_at')->nullable();
        });
    }

    public function test_posts_source_config_builds_consistent_contract(): void
    {
        $config = PostsSourceConfig::fromConfig(config('posts.source'));

        $this->assertSame('posts_source', $config->connection);
        $this->assertTrue($config->qualifiedTableMatchesSchemaTable());
        $this->assertSame('"main"."posts"', $config->tableReference('pgsql'));
        $this->assertSame('posts', $config->tableReference('sqlite'));
    }

    public function test_repository_reads_unpublished_posts_from_posts_source_connection(): void
    {
        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => 'First source post',
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'Already published',
                'media_url' => 'https://example.com/media.jpg',
                'published_at' => now(),
            ],
            [
                'id' => 3,
                'content' => 'Second source post',
                'media_url' => 'https://example.com/photo.jpg',
                'published_at' => null,
            ],
        ]);

        $repository = app(PostsRepository::class);
        $posts = $repository->findUnpublished();

        $this->assertCount(2, $posts);
        $this->assertSame([1, 3], array_map(static fn ($post): int => $post->id, $posts));
        $this->assertSame('First source post', $posts[0]->content);
        $this->assertNull($posts[0]->publishedAt);
        $this->assertSame('https://example.com/photo.jpg', $posts[1]->mediaUrl);
        $this->assertSame(2, $repository->countUnpublished());
    }

    public function test_repository_can_fetch_single_source_post_by_id(): void
    {
        DB::connection('posts_source')->table('posts')->insert([
            'id' => 10,
            'content' => 'Single source post',
            'media_url' => null,
            'published_at' => '2026-04-22 10:00:00',
        ]);

        $repository = app(PostsRepository::class);
        $post = $repository->findById(10);

        $this->assertNotNull($post);
        $this->assertSame(10, $post->id);
        $this->assertSame('Single source post', $post->content);
        $this->assertInstanceOf(CarbonImmutable::class, $post->publishedAt);
        $this->assertNull($repository->findById(9999));
    }
}
