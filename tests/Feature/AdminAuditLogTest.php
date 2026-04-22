<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Features\Publishing\Application\Actions\DryRunPlannedPostAction;
use App\Features\Publishing\Application\Actions\PublishPlannedPostAction;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingPlan;
use App\Models\PostingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_account_creation_and_plan_update_are_logged(): void
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $this->actingAs($user);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Audit channel',
            'external_id' => 'audit-channel',
            'handle' => '@audit_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'platform_account_created',
            'platform_account_id' => $account->getKey(),
        ]);

        /** @var PostingPlan $plan */
        $plan = $account->postingPlan()->firstOrFail();
        $plan->update([
            'timezone' => 'Europe/Kyiv',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'posting_plan_updated',
            'platform_account_id' => $account->getKey(),
            'entity_id' => (string) $plan->getKey(),
        ]);

        $this->assertGreaterThanOrEqual(2, AdminAuditLog::query()->count());
    }

    public function test_platform_account_settings_and_posting_slot_lifecycle_are_logged(): void
    {
        [$user, $account] = $this->makeOwnerAndAccount();
        $this->actingAs($user);

        $account->update([
            'title' => 'Updated audit channel',
            'settings' => [
                'channel_key' => 'updated-channel-key',
                'force_publish' => true,
            ],
        ]);

        $platformUpdateLog = AdminAuditLog::query()
            ->where('action', 'platform_account_updated')
            ->where('platform_account_id', $account->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($platformUpdateLog);
        $this->assertSame('Audit channel', $platformUpdateLog->before['title'] ?? null);
        $this->assertSame('Updated audit channel', $platformUpdateLog->after['title'] ?? null);
        $this->assertSame('updated-channel-key', $platformUpdateLog->after['settings']['channel_key'] ?? null);

        $plan = $account->postingPlan()->firstOrFail();
        $slot = PostingSlot::query()->create([
            'posting_plan_id' => $plan->getKey(),
            'weekday' => 1,
            'time_local' => '09:30',
            'is_enabled' => true,
        ]);

        $slot->update([
            'time_local' => '10:15',
        ]);

        $slot->delete();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'posting_slot_created',
            'platform_account_id' => $account->getKey(),
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'posting_slot_updated',
            'platform_account_id' => $account->getKey(),
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'posting_slot_deleted',
            'platform_account_id' => $account->getKey(),
        ]);
    }

    public function test_dry_run_and_publish_system_actions_are_logged(): void
    {
        [$user, $account] = $this->makeOwnerAndAccount();
        $this->actingAs($user);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'System audit publish body',
            'scheduled_at' => now()->addHour(),
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $dryRun = app(DryRunPlannedPostAction::class)->execute($post, $user);
        $publish = app(PublishPlannedPostAction::class)->execute($post->fresh(), $user, false, 'manual', 'audit-publish-1');

        $this->assertTrue($dryRun->eligible);
        $this->assertTrue($publish->success);

        $dryRunLog = AdminAuditLog::query()
            ->where('action', 'planned_post_dry_run_executed')
            ->where('entity_id', (string) $post->getKey())
            ->latest('id')
            ->first();

        $publishLog = AdminAuditLog::query()
            ->where('action', 'planned_post_publish_attempted')
            ->where('entity_id', (string) $post->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($dryRunLog);
        $this->assertSame(true, $dryRunLog->after['eligible'] ?? null);
        $this->assertSame($user->getKey(), $dryRunLog->user_id);

        $this->assertNotNull($publishLog);
        $this->assertSame('manual', $publishLog->after['attempt_type'] ?? null);
        $this->assertSame(true, $publishLog->after['success'] ?? null);
        $this->assertSame('audit-publish-1', $publishLog->after['idempotency_key'] ?? null);
        $this->assertSame($user->getKey(), $publishLog->user_id);
    }

    /**
     * @return array{0: User, 1: PlatformAccount}
     */
    private function makeOwnerAndAccount(): array
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $this->actingAs($user);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Audit channel',
            'external_id' => 'audit-channel',
            'handle' => '@audit_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $account->users()->syncWithoutDetaching([
            $user->getKey() => ['role' => PlatformAccountRole::Owner->value],
        ]);

        return [$user, $account];
    }
}
