<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PostsSource\Application\Services\SourcePoolService;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlannedPost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourcePoolServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_service_skips_source_pool_during_quiet_hours(): void
    {
        DB::connection('posts_source')->table('posts')->insert([
            'id' => 1,
            'content' => 'A valid source post for daytime publishing',
            'media_url' => null,
            'published_at' => null,
        ]);

        $candidate = app(SourcePoolService::class)->pickCandidate(false, now('UTC')->setTime(1, 30)->toImmutable());

        $this->assertNull($candidate);
    }

    public function test_service_falls_back_from_media_slot_to_text_post(): void
    {
        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => "ТАКИ, МЫ В MAX\n\nFallback text post with enough body to be eligible. This line stays after cleaning.",
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'tiny',
                'media_url' => 'https://example.com/invalid.jpg',
                'published_at' => null,
            ],
        ]);

        $candidate = app(SourcePoolService::class)->pickCandidate(true, now('UTC')->setTime(10, 0)->toImmutable());

        $this->assertNotNull($candidate);
        $this->assertSame(1, $candidate->sourcePost->id);
        $this->assertFalse($candidate->hasMedia);
        $this->assertStringNotContainsString('ТАКИ, МЫ В MAX', $candidate->cleanedContent);
    }

    public function test_service_excludes_source_posts_already_present_in_queue(): void
    {
        $account = $this->makePlatformAccount();

        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => 'Already queued source post with enough text',
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'Fresh source post with enough text for queueing',
                'media_url' => null,
                'published_at' => null,
            ],
        ]);

        PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'posts_source',
            'source_id' => '1',
            'content_text' => 'Already queued source post with enough text',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $candidate = app(SourcePoolService::class)->pickCandidate(false, now('UTC')->setTime(10, 0)->toImmutable());

        $this->assertNotNull($candidate);
        $this->assertSame(2, $candidate->sourcePost->id);
    }

    public function test_service_can_pick_two_distinct_candidates_sequentially_without_duplicates(): void
    {
        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => 'First valid source post with enough text',
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'Second valid source post with enough text',
                'media_url' => null,
                'published_at' => null,
            ],
        ]);

        $repository = app(\App\Features\PostsSource\Application\Contracts\PostsRepository::class);
        $first = DB::connection('posts_source')->transaction(fn () => $repository->pickUnpublished(false, []));
        $second = DB::connection('posts_source')->transaction(fn () => $repository->pickUnpublished(false, [$first?->id ?? 0]));

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
    }

    private function makePlatformAccount(): PlatformAccount
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        return PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Source queue account',
            'external_id' => '@source_queue',
            'handle' => '@source_queue',
            'is_enabled' => true,
            'settings' => [],
        ]);
    }
}
