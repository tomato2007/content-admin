<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Enums\PostingHistoryStatus;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\PostingPlan;
use App\Models\PostingSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalDevWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('email', (string) env('LOCAL_DEV_ADMIN_EMAIL', 'admin@local.test'))
            ->first();

        if (! $admin instanceof User) {
            throw new RuntimeException('Local dev admin must exist before LocalDevWorkspaceSeeder runs.');
        }

        $telegramPlatform = Platform::query()
            ->where('key', 'telegram')
            ->first();

        if (! $telegramPlatform instanceof Platform) {
            throw new RuntimeException('Telegram platform must exist before LocalDevWorkspaceSeeder runs.');
        }

        $account = PlatformAccount::query()->updateOrCreate(
            [
                'platform_id' => $telegramPlatform->getKey(),
                'external_id' => 'content-admin-demo-channel',
            ],
            [
                'title' => 'Demo Telegram Channel',
                'handle' => '@content_admin_demo',
                'is_enabled' => true,
                'credentials_ref' => 'local-demo/telegram',
                'settings' => [
                    'channel_key' => 'content-admin-demo-channel',
                    'target_chat_id' => '@content_admin_demo',
                    'source_chat' => '@content_admin_source',
                    'force_publish' => false,
                ],
            ],
        );

        $account->users()->syncWithoutDetaching([
            $admin->getKey() => ['role' => PlatformAccountRole::Owner->value],
        ]);

        $plan = $account->postingPlan()->first();

        if (! $plan instanceof PostingPlan) {
            throw new RuntimeException('Posting plan must exist for local demo platform account.');
        }

        $plan->update([
            'timezone' => 'Europe/Budapest',
            'quiet_hours_from' => '23:00',
            'quiet_hours_to' => '07:00',
            'rules' => [
                'content_mix' => 'memes, stories, reposts',
                'max_posts_per_day' => '3',
                'approval_mode' => 'manual',
            ],
            'is_active' => true,
        ]);

        foreach ([
            [1, '09:30'],
            [3, '13:00'],
            [5, '18:45'],
        ] as [$weekday, $timeLocal]) {
            PostingSlot::query()->updateOrCreate(
                [
                    'posting_plan_id' => $plan->getKey(),
                    'weekday' => $weekday,
                    'time_local' => $timeLocal,
                ],
                [
                    'is_enabled' => true,
                ],
            );
        }

        $timelineBase = CarbonImmutable::now('Europe/Budapest');

        PlannedPost::query()->updateOrCreate(
            [
                'platform_account_id' => $account->getKey(),
                'source_type' => 'local_seed',
                'source_id' => 'demo-pending-review',
            ],
            [
                'content_snapshot' => [
                    'source_chat' => '@content_admin_source',
                    'source_message_id' => '1001',
                ],
                'content_text' => 'Pending review demo post for queue moderation checks.',
                'scheduled_at' => $timelineBase->addDay()->setTime(9, 30),
                'status' => PlannedPostStatus::Scheduled,
                'moderation_status' => ModerationStatus::PendingReview,
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
                'notes' => 'Seeded local moderation example.',
            ],
        );

        PlannedPost::query()->updateOrCreate(
            [
                'platform_account_id' => $account->getKey(),
                'source_type' => 'local_seed',
                'source_id' => 'demo-approved',
            ],
            [
                'content_snapshot' => [
                    'source_chat' => '@content_admin_source',
                    'source_message_id' => '1002',
                ],
                'content_text' => 'Approved demo post ready for dry-run or manual publish.',
                'scheduled_at' => $timelineBase->addDays(2)->setTime(13, 0),
                'status' => PlannedPostStatus::Scheduled,
                'moderation_status' => ModerationStatus::Approved,
                'approved_by' => $admin->getKey(),
                'approved_at' => $timelineBase->subHour(),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
                'notes' => 'Seeded local publish-ready example.',
            ],
        );

        $failedPost = PlannedPost::query()->updateOrCreate(
            [
                'platform_account_id' => $account->getKey(),
                'source_type' => 'local_seed',
                'source_id' => 'demo-failed',
            ],
            [
                'content_snapshot' => [
                    'source_chat' => '@content_admin_source',
                    'source_message_id' => '1003',
                ],
                'content_text' => 'Failed demo post so retry flow has a visible example.',
                'scheduled_at' => $timelineBase->subDay()->setTime(18, 45),
                'status' => PlannedPostStatus::Failed,
                'moderation_status' => ModerationStatus::Approved,
                'approved_by' => $admin->getKey(),
                'approved_at' => $timelineBase->subDays(2),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
                'notes' => 'Seeded local failed publish example.',
            ],
        );

        $publishedPost = PlannedPost::query()->updateOrCreate(
            [
                'platform_account_id' => $account->getKey(),
                'source_type' => 'local_seed',
                'source_id' => 'demo-published',
            ],
            [
                'content_snapshot' => [
                    'source_chat' => '@content_admin_source',
                    'source_message_id' => '1004',
                ],
                'content_text' => 'Published demo post with history entry for delivery visibility.',
                'scheduled_at' => $timelineBase->subDays(3)->setTime(13, 0),
                'status' => PlannedPostStatus::Published,
                'moderation_status' => ModerationStatus::Approved,
                'approved_by' => $admin->getKey(),
                'approved_at' => $timelineBase->subDays(4),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
                'notes' => 'Seeded local published example.',
            ],
        );

        PostingHistory::query()->updateOrCreate(
            [
                'idempotency_key' => 'local-seed-published-demo',
            ],
            [
                'platform_account_id' => $account->getKey(),
                'planned_post_id' => $publishedPost->getKey(),
                'status' => PostingHistoryStatus::Sent,
                'attempt_type' => 'manual',
                'scheduled_at' => $publishedPost->scheduled_at,
                'sent_at' => $timelineBase->subDays(3)->setTime(13, 2),
                'provider_message_id' => 'demo-message-1004',
                'triggered_by' => $admin->getKey(),
                'payload' => [
                    'mode' => 'text-card',
                    'seeded' => true,
                ],
                'response' => [
                    'status' => 'posted',
                    'message_id' => 'demo-message-1004',
                ],
                'error' => null,
            ],
        );

        PostingHistory::query()->updateOrCreate(
            [
                'idempotency_key' => 'local-seed-failed-demo',
            ],
            [
                'platform_account_id' => $account->getKey(),
                'planned_post_id' => $failedPost->getKey(),
                'status' => PostingHistoryStatus::Failed,
                'attempt_type' => 'retry',
                'scheduled_at' => $failedPost->scheduled_at,
                'sent_at' => null,
                'provider_message_id' => null,
                'triggered_by' => $admin->getKey(),
                'payload' => [
                    'mode' => 'text-card',
                    'seeded' => true,
                ],
                'response' => [
                    'status' => 'failed',
                ],
                'error' => 'Seeded local failure example.',
            ],
        );

    }
}
