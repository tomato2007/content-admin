<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlannedPostWorkflowService
{
    public function approve(PlannedPost $plannedPost, User $actor): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => CarbonImmutable::now(),
                'status' => $plannedPost->scheduled_at !== null ? PlannedPostStatus::Scheduled : PlannedPostStatus::Draft,
            ]);

            $this->log('planned_post_approved', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }

    public function reject(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::Rejected,
                'status' => PlannedPostStatus::Cancelled,
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_rejected', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }

    public function requestDelete(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::DeleteRequested,
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_delete_requested', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }

    public function confirmDelete(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::DeleteConfirmed,
                'status' => PlannedPostStatus::Cancelled,
                'delete_confirmed_by' => $actor->getKey(),
                'delete_confirmed_at' => CarbonImmutable::now(),
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_delete_confirmed', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $replacementData
     */
    public function replace(PlannedPost $plannedPost, User $actor, array $replacementData): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor, $replacementData): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::NeedsReplacement,
                'status' => PlannedPostStatus::Replaced,
                'notes' => $this->mergeNotes($plannedPost->notes, $replacementData['reason'] ?? 'Replacement requested'),
            ]);

            $replacement = PlannedPost::query()->create([
                'platform_account_id' => $plannedPost->platform_account_id,
                'source_type' => $replacementData['source_type'] ?? $plannedPost->source_type,
                'source_id' => $replacementData['source_id'] ?? null,
                'content_text' => $replacementData['content_text'] ?? $plannedPost->content_text,
                'scheduled_at' => $replacementData['scheduled_at'] ?? $plannedPost->scheduled_at,
                'status' => ($replacementData['scheduled_at'] ?? $plannedPost->scheduled_at) !== null
                    ? PlannedPostStatus::Scheduled
                    : PlannedPostStatus::Draft,
                'moderation_status' => ModerationStatus::PendingReview,
                'replace_of_id' => $plannedPost->getKey(),
                'notes' => $replacementData['notes'] ?? null,
            ]);

            $this->log('planned_post_replaced', $plannedPost, $actor, $before, [
                'original' => $plannedPost->fresh()->toArray(),
                'replacement_id' => $replacement->getKey(),
            ]);

            return $replacement->fresh();
        });
    }

    public function reschedule(PlannedPost $plannedPost, User $actor, string $scheduledAt, ?string $notes = null): PlannedPost
    {
        return DB::transaction(function () use ($plannedPost, $actor, $scheduledAt, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'scheduled_at' => CarbonImmutable::parse($scheduledAt),
                'status' => PlannedPostStatus::Scheduled,
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_rescheduled', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(string $action, PlannedPost $plannedPost, User $actor, ?array $before, ?array $after): void
    {
        AdminAuditLog::logAction(
            action: $action,
            userId: $actor->getKey(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: $before,
            after: $after,
        );
    }

    private function mergeNotes(?string $current, ?string $new): ?string
    {
        $new = trim((string) $new);

        if ($new === '') {
            return $current;
        }

        $current = trim((string) $current);

        return $current === '' ? $new : $current."\n\n".$new;
    }
}
