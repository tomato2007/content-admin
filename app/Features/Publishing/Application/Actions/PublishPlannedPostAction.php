<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Actions;

use App\Features\Publishing\Application\Orchestration\PublishPlannedPostOrchestrator;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\User;
use App\Services\Publishing\Data\PublishResult;
use RuntimeException;

class PublishPlannedPostAction
{
    public function __construct(
        private readonly PublishPlannedPostOrchestrator $publishPlannedPostOrchestrator,
    ) {}

    public function execute(PlannedPost $plannedPost, User $actor, bool $force = false, string $attemptType = 'manual', ?string $idempotencyKey = null): PublishResult
    {
        $plannedPost->loadMissing('platformAccount');

        if ($plannedPost->platformAccount === null) {
            throw new RuntimeException('Planned post is not attached to a platform account.');
        }

        if (! $actor->canManagePostingPlan($plannedPost->platformAccount)) {
            throw new RuntimeException('You are not allowed to publish this planned post.');
        }

        $before = [
            'status' => $plannedPost->status?->value,
            'moderation_status' => $plannedPost->moderation_status?->value,
            'scheduled_at' => $plannedPost->scheduled_at?->toDateTimeString(),
        ];

        $idempotencyKey ??= sprintf('planned-post-%d-%s', $plannedPost->getKey(), $attemptType);

        $result = $this->publishPlannedPostOrchestrator->execute(
            $plannedPost,
            $actor->getKey(),
            $force,
            $attemptType,
            $idempotencyKey,
        );

        AdminAuditLog::logAction(
            action: 'planned_post_publish_attempted',
            userId: $actor->getKey(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: $before,
            after: [
                'attempt_type' => $attemptType,
                'force' => $force,
                'success' => $result->success,
                'status' => $result->status,
                'provider_message_id' => $result->providerMessageId,
                'error' => $result->error,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        return $result;
    }
}
