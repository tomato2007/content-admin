<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PostsSource\Application\Actions\GeneratePlannedPostsFromSourceAction;
use App\Models\AdminAuditLog;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlannedPost;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeneratePlannedPostsFromSourceActionTest extends TestCase
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
        config()->set('posts.scheduler.quiet_hours_start_utc', '00:00');
        config()->set('posts.scheduler.quiet_hours_end_utc', '06:00');

        Schema::connection('posts_source')->dropIfExists('posts');
        Schema::connection('posts_source')->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->text('content')->nullable();
            $table->string('media_url')->nullable();
            $table->timestamp('published_at')->nullable();
        });
    }

    public function test_action_generates_pending_review_planned_posts_from_source_pool(): void
    {
        [$actor, $account] = $this->makeActorAndAccount();

        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => 'Source post one with enough text to become a planned post',
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'Source post two with enough text to become a planned post',
                'media_url' => 'https://example.com/two.jpg',
                'published_at' => null,
            ],
        ]);

        $result = app(GeneratePlannedPostsFromSourceAction::class)->execute($account, $actor, 10);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['no_candidates']);

        $plannedPosts = PlannedPost::query()->where('platform_account_id', $account->getKey())->orderBy('id')->get();

        $this->assertCount(2, $plannedPosts);
        $this->assertSame('posts_source', $plannedPosts[0]->source_type);
        $this->assertSame('1', $plannedPosts[0]->source_id);
        $this->assertSame(PlannedPostStatus::Draft, $plannedPosts[0]->status);
        $this->assertSame(ModerationStatus::PendingReview, $plannedPosts[0]->moderation_status);
        $this->assertSame('posts_source', $plannedPosts[0]->content_snapshot['generated_from'] ?? null);
        $this->assertSame('https://example.com/two.jpg', $plannedPosts[1]->content_snapshot['source_media_url'] ?? null);
    }

    public function test_action_writes_batch_generation_audit_log(): void
    {
        [$actor, $account] = $this->makeActorAndAccount();

        DB::connection('posts_source')->table('posts')->insert([
            'id' => 10,
            'content' => 'Audit source post with enough text to become a planned post',
            'media_url' => null,
            'published_at' => null,
        ]);

        app(GeneratePlannedPostsFromSourceAction::class)->execute($account, $actor, 10);

        $auditLog = AdminAuditLog::query()
            ->where('action', 'planned_posts_generated_from_source')
            ->where('platform_account_id', $account->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($actor->getKey(), $auditLog->user_id);
        $this->assertSame(1, $auditLog->after['created'] ?? null);
        $this->assertSame('posts_source', $auditLog->after['source_type'] ?? null);
        $this->assertSame(10, $auditLog->after['batch_limit'] ?? null);
        $this->assertNotEmpty($auditLog->after['created_ids'] ?? []);
    }

    /**
     * @return array{0:User,1:PlatformAccount}
     */
    private function makeActorAndAccount(): array
    {
        $actor = User::factory()->create();

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Generated queue account',
            'external_id' => '@generated_queue',
            'handle' => '@generated_queue',
            'is_enabled' => true,
            'settings' => [],
        ]);

        return [$actor, $account];
    }
}
