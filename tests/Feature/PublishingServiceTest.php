<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Enums\PostingHistoryStatus;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\User;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\PublishingService;
use App\Services\Publishing\TelegramPublisherDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_records_history_and_marks_post_as_published(): void
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $account->users()->attach($user->getKey(), ['role' => PlatformAccountRole::Owner->value]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'scheduled_at' => now()->addHour(),
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $result = app(PublishingService::class)->publish($post, $user->getKey(), false, 'manual', 'pub-test-1');

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);

        $post->refresh();
        $this->assertSame(PlannedPostStatus::Published, $post->status);

        $history = PostingHistory::query()->where('planned_post_id', $post->getKey())->first();
        $this->assertNotNull($history);
        $this->assertSame(PostingHistoryStatus::Sent, $history->status);
        $this->assertSame('manual', $history->attempt_type);
        $this->assertSame('pub-test-1', $history->idempotency_key);
        $this->assertSame($user->getKey(), $history->triggered_by);
    }

    public function test_publish_is_idempotent_for_same_key(): void
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $account->users()->attach($user->getKey(), ['role' => PlatformAccountRole::Owner->value]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'scheduled_at' => now()->addHour(),
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $first = app(PublishingService::class)->publish($post, $user->getKey(), false, 'manual', 'pub-test-2');
        $second = app(PublishingService::class)->publish($post->fresh(), $user->getKey(), false, 'manual', 'pub-test-2');

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame($first->providerMessageId, $second->providerMessageId);
        $this->assertSame(1, PostingHistory::query()->where('planned_post_id', $post->getKey())->count());
    }

    public function test_telegram_driver_can_parse_dry_run_json(): void
    {
        $driver = new class extends TelegramPublisherDriver
        {
            protected function runScript(PublishRequest $request, bool $dryRun): array
            {
                return [
                    'status' => 'dry_run',
                    'eligible' => true,
                    'cleaned_text' => 'Clean text',
                    'mode' => 'text-card',
                    'meta' => ['target' => '@anecdots_mems'],
                ];
            }
        };

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $result = $driver->dryRun(new PublishRequest($post, $account));

        $this->assertTrue($result->eligible);
        $this->assertSame('text-card', $result->mode);
        $this->assertSame('Clean text', $result->cleanedText);
    }
}
