<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Enums\PostingHistoryStatus;
use App\Features\PlannedPosts\Application\Actions\ApprovePlannedPostAction;
use App\Features\PostsSource\Application\Actions\GeneratePlannedPostsFromSourceAction;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeneratedQueueWorkflowTest extends TestCase
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

    public function test_generated_post_moves_to_approved_scheduled_and_can_be_auto_published_without_duplicates(): void
    {
        [$actor, $account] = $this->makeActorAndAccount();

        DB::connection('posts_source')->table('posts')->insert([
            [
                'id' => 1,
                'content' => 'Generated source post one with enough text to be queued and published',
                'media_url' => null,
                'published_at' => null,
            ],
            [
                'id' => 2,
                'content' => 'Generated source post two with enough text to remain available later',
                'media_url' => null,
                'published_at' => null,
            ],
        ]);

        app(GeneratePlannedPostsFromSourceAction::class)->execute($account, $actor, 1);

        $generatedPost = $account->plannedPosts()->latest('id')->firstOrFail();
        $this->assertSame(PlannedPostStatus::Draft, $generatedPost->status);
        $this->assertSame(ModerationStatus::PendingReview, $generatedPost->moderation_status);
        $this->assertSame('Ожидание модерации', $generatedPost->queueStateLabel());

        $generatedPost->update(['scheduled_at' => '2026-04-23 10:30:00']);
        $approvedPost = app(ApprovePlannedPostAction::class)->execute($generatedPost->fresh(), $actor);

        $this->assertSame(ModerationStatus::Approved, $approvedPost->moderation_status);
        $this->assertSame(PlannedPostStatus::Scheduled, $approvedPost->status);
        $this->assertSame('Подтвержден, ожидает публикации', $approvedPost->queueStateLabel());

        $this->artisan('publishing:run-scheduled', [
            '--now' => '2026-04-23 11:00:00',
            '--sync' => true,
        ])->assertSuccessful();

        $approvedPost->refresh();
        $this->assertSame(PlannedPostStatus::Published, $approvedPost->status);
        $this->assertSame('Опубликован', $approvedPost->queueStateLabel());

        $history = PostingHistory::query()->where('planned_post_id', $approvedPost->getKey())->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(PostingHistoryStatus::Sent, $history->status);

        $secondGeneration = app(GeneratePlannedPostsFromSourceAction::class)->execute($account, $actor, 10);
        $sourceIds = $account->plannedPosts()->pluck('source_id')->filter()->values()->all();
        $sourcePostOne = DB::connection('posts_source')->table('posts')->where('id', 1)->first();

        $this->assertContains('1', $sourceIds);
        $this->assertContains('2', $sourceIds);
        $this->assertCount(count(array_unique($sourceIds)), $sourceIds);
        $this->assertSame(1, $secondGeneration['created']);
        $this->assertNotNull($sourcePostOne?->published_at);
    }

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
            'title' => 'Generated workflow account',
            'external_id' => '@generated_workflow',
            'handle' => '@generated_workflow',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $account->users()->attach($actor->getKey(), ['role' => PlatformAccountRole::Owner->value]);

        return [$actor, $account];
    }
}
