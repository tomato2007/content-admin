<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Kernel;
use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PostingHistoryStatus;
use App\Jobs\PublishScheduledPlannedPostJob;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use ReflectionMethod;
use Tests\TestCase;

class ScheduledPublishingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_only_for_due_approved_scheduled_posts(): void
    {
        Bus::fake();

        [$duePost, $futurePost, $pendingPost] = $this->makeScheduledPublishingFixtures();

        $this->artisan('publishing:run-scheduled', [
            '--now' => '2026-04-21 12:00:00',
            '--limit' => 10,
        ])->expectsOutput('Dispatched 1 scheduled publish job(s) in queued mode.')
            ->assertSuccessful();

        Bus::assertDispatched(PublishScheduledPlannedPostJob::class, function (PublishScheduledPlannedPostJob $job) use ($duePost): bool {
            return $job->plannedPostId === $duePost->getKey();
        });
        Bus::assertNotDispatched(PublishScheduledPlannedPostJob::class, function (PublishScheduledPlannedPostJob $job) use ($futurePost, $pendingPost): bool {
            return in_array($job->plannedPostId, [$futurePost->getKey(), $pendingPost->getKey()], true);
        });
    }

    public function test_command_can_publish_due_posts_inline_in_sync_mode(): void
    {
        [$duePost] = $this->makeScheduledPublishingFixtures();

        $this->artisan('publishing:run-scheduled', [
            '--now' => '2026-04-21 12:00:00',
            '--sync' => true,
        ])->expectsOutput('Dispatched 1 scheduled publish job(s) in sync mode.')
            ->assertSuccessful();

        $duePost->refresh();

        $this->assertSame(PlannedPostStatus::Published, $duePost->status);

        $history = PostingHistory::query()->where('planned_post_id', $duePost->getKey())->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(PostingHistoryStatus::Sent, $history->status);
        $this->assertSame('scheduled', $history->attempt_type);
        $this->assertNull($history->triggered_by);
        $this->assertStringStartsWith('planned-post-'.$duePost->getKey().'-scheduled-', (string) $history->idempotency_key);

        $auditLog = AdminAuditLog::query()
            ->where('action', 'planned_post_auto_publish_attempted')
            ->where('entity_id', (string) $duePost->getKey())
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertNull($auditLog->user_id);
        $this->assertSame('scheduled', $auditLog->after['attempt_type'] ?? null);
        $this->assertSame(true, $auditLog->after['success'] ?? null);
    }

    public function test_scheduler_registers_scheduled_publishing_command_every_minute(): void
    {
        $kernel = app(Kernel::class);
        $schedule = app(Schedule::class);

        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'publishing:run-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    /**
     * @return array{0: PlannedPost, 1: PlannedPost, 2: PlannedPost}
     */
    private function makeScheduledPublishingFixtures(): array
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Auto publish channel',
            'external_id' => '@auto_publish',
            'handle' => '@auto_publish',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $duePost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Due scheduled post',
            'scheduled_at' => '2026-04-21 11:30:00',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $futurePost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Future scheduled post',
            'scheduled_at' => '2026-04-21 13:30:00',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $pendingPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Pending moderation post',
            'scheduled_at' => '2026-04-21 10:00:00',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        return [$duePost, $futurePost, $pendingPost];
    }
}
