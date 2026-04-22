<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Features\PlannedPosts\Application\Actions\ApprovePlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\ConfirmPlannedPostDeletionAction;
use App\Features\PlannedPosts\Application\Actions\RejectPlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\ReplacePlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\RequestPlannedPostDeletionAction;
use App\Features\PlannedPosts\Application\Actions\ReschedulePlannedPostAction;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

        app(ApprovePlannedPostAction::class)->execute($plannedPost, $owner);
        $plannedPost->refresh();

        $this->assertSame(ModerationStatus::Approved, $plannedPost->moderation_status);
        $this->assertSame(PlannedPostStatus::Scheduled, $plannedPost->status);
        $this->assertSame($owner->getKey(), $plannedPost->approved_by);

        app(RequestPlannedPostDeletionAction::class)->execute($plannedPost, $owner, 'Delete this slot');
        $plannedPost->refresh();
        $this->assertSame(ModerationStatus::DeleteRequested, $plannedPost->moderation_status);

        app(ConfirmPlannedPostDeletionAction::class)->execute($plannedPost, $owner, 'Confirmed');
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

        $replacement = app(ReplacePlannedPostAction::class)->execute($plannedPost, $owner, [
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

        app(ReschedulePlannedPostAction::class)->execute($replacement, $owner, '2026-04-14 18:30:00', 'Move to evening');
        $replacement->refresh();

        $this->assertSame(PlannedPostStatus::Scheduled, $replacement->status);
        $this->assertSame('2026-04-14 18:30:00', $replacement->scheduled_at?->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Move to evening', (string) $replacement->notes);
    }

    public function test_invalid_transitions_are_rejected_for_terminal_and_non_matching_states(): void
    {
        [$owner, $account] = $this->makeOwnerAndAccount('x');
        $this->actingAs($owner);

        $publishedPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Already published',
            'scheduled_at' => '2026-04-12 10:00:00',
            'status' => PlannedPostStatus::Published,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $pendingPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Still pending',
            'scheduled_at' => '2026-04-13 11:00:00',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $replacementSource = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Needs replacement',
            'scheduled_at' => '2026-04-14 12:00:00',
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        app(ReplacePlannedPostAction::class)->execute($replacementSource, $owner, [
            'source_type' => 'manual',
            'content_text' => 'Replacement draft',
            'scheduled_at' => '2026-04-15 14:00:00',
        ]);
        $replacementSource->refresh();

        try {
            app(ApprovePlannedPostAction::class)->execute($publishedPost, $owner);
            $this->fail('Approve should be blocked for published posts.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot be approved in its current state.', $e->getMessage());
        }

        try {
            app(RejectPlannedPostAction::class)->execute($publishedPost, $owner, 'Too late');
            $this->fail('Reject should be blocked for published posts.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot be rejected in its current state.', $e->getMessage());
        }

        try {
            app(ConfirmPlannedPostDeletionAction::class)->execute($pendingPost, $owner, 'No request yet');
            $this->fail('Confirm delete should require delete_requested state.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot confirm deletion in its current state.', $e->getMessage());
        }

        try {
            app(RequestPlannedPostDeletionAction::class)->execute($publishedPost, $owner, 'Remove published');
            $this->fail('Delete request should be blocked for terminal posts.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot request deletion in its current state.', $e->getMessage());
        }

        try {
            app(ReschedulePlannedPostAction::class)->execute($replacementSource, $owner, '2026-04-16 18:30:00', 'Move replacement source');
            $this->fail('Reschedule should be blocked for replaced posts.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot be rescheduled in its current state.', $e->getMessage());
        }

        try {
            app(ReplacePlannedPostAction::class)->execute($publishedPost, $owner, [
                'source_type' => 'manual',
                'content_text' => 'Illegal replacement',
            ]);
            $this->fail('Replace should be blocked for published posts.');
        } catch (RuntimeException $e) {
            $this->assertSame('This planned post cannot be replaced in its current state.', $e->getMessage());
        }
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
