<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Orchestration;

use App\Enums\PostingHistoryStatus;
use App\Models\PlannedPost;
use App\Models\PostingHistory;
use App\Services\Publishing\Data\PublishResult;
use App\Services\Publishing\PublishingService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PublishPlannedPostOrchestrator
{
    public function __construct(
        private readonly PublishingService $publishingService,
    ) {}

    public function execute(
        PlannedPost $plannedPost,
        ?int $triggeredByUserId,
        bool $force,
        string $attemptType,
        string $idempotencyKey,
    ): PublishResult {
        $plannedPost->loadMissing('platformAccount.platform');

        $lock = Cache::lock($this->lockKey($plannedPost), 30);

        if (! $lock->get()) {
            throw new RuntimeException('Another publish attempt is already running for this planned post.');
        }

        try {
            $existing = $this->findSuccessfulAttempt($plannedPost, $idempotencyKey);

            if ($existing !== null) {
                return $this->mapHistoryToPublishResult($existing);
            }

            return $this->publishingService->publish(
                $plannedPost,
                $triggeredByUserId,
                $force,
                $attemptType,
                $idempotencyKey,
            );
        } finally {
            $lock->release();
        }
    }

    private function lockKey(PlannedPost $plannedPost): string
    {
        return 'publishing:planned-post:'.$plannedPost->getKey();
    }

    private function findSuccessfulAttempt(PlannedPost $plannedPost, string $idempotencyKey): ?PostingHistory
    {
        return PostingHistory::query()
            ->where('planned_post_id', $plannedPost->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', PostingHistoryStatus::Sent)
            ->latest('id')
            ->first();
    }

    private function mapHistoryToPublishResult(PostingHistory $history): PublishResult
    {
        return new PublishResult(
            success: true,
            status: $history->status->value,
            providerMessageId: $history->provider_message_id,
            payload: $history->payload,
            response: $history->response,
            error: $history->error,
            sentAt: $history->sent_at?->toImmutable(),
            mode: (string) ($history->payload['mode'] ?? 'text'),
            externalReference: $history->idempotency_key,
        );
    }
}
