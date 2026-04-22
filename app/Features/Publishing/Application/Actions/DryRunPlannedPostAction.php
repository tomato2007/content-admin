<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Actions;

use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\User;
use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\PublishingService;

class DryRunPlannedPostAction
{
    public function __construct(
        private readonly PublishingService $publishingService,
    ) {}

    public function execute(PlannedPost $plannedPost, ?User $actor = null): DryRunResult
    {
        $plannedPost->loadMissing('platformAccount.platform');
        $actor ??= auth()->user();

        $result = $this->publishingService->dryRun($plannedPost);

        AdminAuditLog::logAction(
            action: 'planned_post_dry_run_executed',
            userId: $actor?->getKey(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: [
                'status' => $plannedPost->status?->value,
                'moderation_status' => $plannedPost->moderation_status?->value,
                'scheduled_at' => $plannedPost->scheduled_at?->toDateTimeString(),
            ],
            after: [
                'eligible' => $result->eligible,
                'mode' => $result->mode,
                'reason' => $result->reason,
                'meta' => $result->meta,
            ],
        );

        return $result;
    }
}
