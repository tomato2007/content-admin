<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Actions;

use App\Features\Publishing\Application\Orchestration\PublishPlannedPostOrchestrator;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Services\Publishing\Data\PublishResult;
use Carbon\CarbonImmutable;
use RuntimeException;

class PublishScheduledPlannedPostAction
{
    public function __construct(
        private readonly PublishPlannedPostOrchestrator $publishPlannedPostOrchestrator,
    ) {}

    public function execute(PlannedPost $plannedPost): PublishResult
    {
        $plannedPost->loadMissing('platformAccount');

        if ($plannedPost->platformAccount === null) {
            throw new RuntimeException('Planned post is not attached to a platform account.');
        }

        $before = [
            'status' => $plannedPost->status?->value,
            'moderation_status' => $plannedPost->moderation_status?->value,
            'scheduled_at' => $plannedPost->scheduled_at?->toDateTimeString(),
        ];

        $idempotencyKey = $this->scheduledIdempotencyKey($plannedPost);
        $result = $this->publishPlannedPostOrchestrator->execute(
            $plannedPost,
            null,
            false,
            'scheduled',
            $idempotencyKey,
        );

        AdminAuditLog::logAction(
            action: 'planned_post_auto_publish_attempted',
            userId: null,
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: $before,
            after: [
                'attempt_type' => 'scheduled',
                'success' => $result->success,
                'status' => $result->status,
                'provider_message_id' => $result->providerMessageId,
                'error' => $result->error,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        return $result;
    }

    private function scheduledIdempotencyKey(PlannedPost $plannedPost): string
    {
        $scheduledAt = $plannedPost->scheduled_at?->toImmutable() ?? CarbonImmutable::now('UTC');

        return sprintf(
            'planned-post-%d-scheduled-%s',
            $plannedPost->getKey(),
            $scheduledAt->setTimezone('UTC')->format('YmdHis'),
        );
    }
}
