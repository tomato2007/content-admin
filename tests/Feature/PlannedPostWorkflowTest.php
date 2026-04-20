<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\PlannedPostWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedPostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_request_delete_and_confirm_delete_flow(): void
    {
        [$owner, $account] = $this->makeOwnerAndAccount();
        $this->actingAs($owner);

        $plannedPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test moderation post',
            'scheduled_at' => '2026-04-12 10:00:00',
        ]);

        $service = app(PlannedPostWorkflowService::class);
        $service->approve($plannedPost, $owner);
        $plannedPost->refresh();

        $this->assertSame(ModerationStatus::Approved, $plannedPost->moderation_status);
        $this->assertSame(PlannedPostStatus::Scheduled, $plannedPost->status);
        $this->assertSame($owner->getKey(), $plannedPost->approved_by);

        $service->requestDelete($plannedPost, $owner, 'Delete this slot');
        $plannedPost->refresh();
        $this->assertSame(ModerationStatus::DeleteRequested, $plannedPost->moderation_status);

        $service->confirmDelete($plannedPost, $owner, 'Confirmed');
        $plannedPost->refresh();

        $this->assertSame(ModerationStatus::DeleteConfirmed, $plannedPost->moderation_status);
        $this->assertSame(PlannedPostStatus::Cancelled, $plannedPost->status);
        $this->assertSame($owner->getKey(), $plannedPost->delete_confirmed_by);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'planned_post_delete_confirmed',
            'platform_account_id' => $account->getKey(),
        ]);
    }

    public function test_replace_and_reschedule_create_a_new_planned_post_without_overwriting_original(): void
    {
        [$owner, $account] = $this->makeOwnerAndAccount('vk');
        $this->actingAs($owner);

        $plannedPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Original content',
            'scheduled_at' => '2026-04-12 10:00:00',
        ]);

        $service = app(PlannedPostWorkflowService::class);

        $replacement = $service->replace($plannedPost, $owner, [
            'source_type' => 'manual',
            'content_text' => 'Replacement content',
            'scheduled_at' => '2026-04-13 12:00:00',
            'reason' => 'Needs a better variant',
        ]);

        $plannedPost->refresh();

        $this->assertSame(PlannedPostStatus::Replaced, $plannedPost->status);
        $this->assertSame(ModerationStatus::NeedsReplacement, $plannedPost->moderation_status);
        $this->assertSame($plannedPost->getKey(), $replacement->replace_of_id);
        $this->assertSame('Replacement content', $replacement->content_text);
        $this->assertSame(ModerationStatus::PendingReview, $replacement->moderation_status);

        $service->reschedule($replacement, $owner, '2026-04-14 18:30:00', 'Move to evening');
        $replacement->refresh();

        $this->assertSame(PlannedPostStatus::Scheduled, $replacement->status);
        $this->assertSame('2026-04-14 18:30:00', $replacement->scheduled_at?->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Move to evening', (string) $replacement->notes);
    }

    /**
     * @return array{0: User, 1: PlatformAccount}
     */
    private function makeOwnerAndAccount(string $platformKey = 'telegram'): array
    {
        $platform = Platform::query()->create([
            'key' => $platformKey,
            'name' => strtoupper($platformKey),
            'driver' => $platformKey,
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => $platformKey.' account',
            'external_id' => $platformKey.'-account',
            'handle' => '@'.$platformKey.'_account',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $owner = User::factory()->create();
        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);

        return [$owner, $account];
    }
}
