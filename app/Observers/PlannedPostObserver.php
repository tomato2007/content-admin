<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;

class PlannedPostObserver
{
    public function creating(PlannedPost $plannedPost): void
    {
        if ($plannedPost->created_by === null) {
            $plannedPost->created_by = auth()->id();
        }

        if ($plannedPost->updated_by === null) {
            $plannedPost->updated_by = auth()->id();
        }

        if ($plannedPost->status === null) {
            $plannedPost->status = PlannedPostStatus::Draft;
        }

        if ($plannedPost->moderation_status === null) {
            $plannedPost->moderation_status = ModerationStatus::PendingReview;
        }

        $this->normalizeStatus($plannedPost);
    }

    public function updating(PlannedPost $plannedPost): void
    {
        $plannedPost->updated_by = auth()->id() ?? $plannedPost->updated_by;
        $this->normalizeStatus($plannedPost);
    }

    public function created(PlannedPost $plannedPost): void
    {
        AdminAuditLog::logAction(
            action: 'planned_post_created',
            userId: auth()->id(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: null,
            after: $plannedPost->toArray(),
        );
    }

    public function updated(PlannedPost $plannedPost): void
    {
        AdminAuditLog::logAction(
            action: 'planned_post_updated',
            userId: auth()->id(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: $plannedPost->getOriginal(),
            after: $plannedPost->getChanges(),
        );
    }

    private function normalizeStatus(PlannedPost $plannedPost): void
    {
        if ($plannedPost->status === PlannedPostStatus::Cancelled || $plannedPost->status === PlannedPostStatus::Replaced) {
            return;
        }

        if ($plannedPost->moderation_status === ModerationStatus::Rejected || $plannedPost->moderation_status === ModerationStatus::DeleteConfirmed) {
            $plannedPost->status = PlannedPostStatus::Cancelled;

            return;
        }

        if ($plannedPost->moderation_status === ModerationStatus::NeedsReplacement) {
            $plannedPost->status = PlannedPostStatus::Replaced;

            return;
        }

        if ($plannedPost->scheduled_at !== null && $plannedPost->status === PlannedPostStatus::Draft) {
            $plannedPost->status = PlannedPostStatus::Scheduled;
        }
    }
}
