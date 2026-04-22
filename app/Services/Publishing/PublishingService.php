<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PostingHistoryStatus;
use App\Models\PlannedPost;
use App\Models\PostingHistory;
use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\Contracts\PublisherDriverResolverInterface;
use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\Data\PublishResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublishingService
{
    public function __construct(
        private readonly PublisherDriverResolverInterface $publisherDriverResolver,
    ) {}

    public function dryRun(PlannedPost $plannedPost): DryRunResult
    {
        $plannedPost->loadMissing('platformAccount.platform');

        return $this->resolveDriver($plannedPost)->dryRun(new PublishRequest(
            plannedPost: $plannedPost,
            platformAccount: $plannedPost->platformAccount,
        ));
    }

    public function publish(PlannedPost $plannedPost, ?int $triggeredByUserId = null, bool $force = false, string $attemptType = 'manual', ?string $idempotencyKey = null): PublishResult
    {
        $plannedPost->loadMissing('platformAccount.platform');

        $idempotencyKey ??= sprintf('planned-post-%d', $plannedPost->getKey());

        $this->guardCanPublish($plannedPost);

        return DB::transaction(function () use ($plannedPost, $triggeredByUserId, $force, $attemptType, $idempotencyKey): PublishResult {
            $plannedPost->refresh();
            $this->guardCanPublish($plannedPost);

            $plannedPost->update([
                'status' => PlannedPostStatus::Publishing,
            ]);

            $request = new PublishRequest(
                plannedPost: $plannedPost,
                platformAccount: $plannedPost->platformAccount,
                force: $force,
                triggeredByUserId: $triggeredByUserId,
                idempotencyKey: $idempotencyKey,
                attemptType: $attemptType,
            );

            $result = $this->resolveDriver($plannedPost)->publish($request);

            PostingHistory::query()->create([
                'platform_account_id' => $plannedPost->platform_account_id,
                'planned_post_id' => $plannedPost->getKey(),
                'status' => $result->success ? PostingHistoryStatus::Sent : PostingHistoryStatus::Failed,
                'attempt_type' => $attemptType,
                'scheduled_at' => $plannedPost->scheduled_at,
                'sent_at' => $result->sentAt,
                'provider_message_id' => $result->providerMessageId,
                'triggered_by' => $triggeredByUserId,
                'idempotency_key' => $idempotencyKey,
                'payload' => array_merge($result->payload ?? [], [
                    'mode' => $result->mode,
                ]),
                'response' => $result->response,
                'error' => $result->error,
            ]);

            $plannedPost->update([
                'status' => $result->success ? PlannedPostStatus::Published : PlannedPostStatus::Failed,
                'moderation_status' => $result->success ? ModerationStatus::Approved : $plannedPost->moderation_status,
            ]);

            return $result;
        });
    }

    private function guardCanPublish(PlannedPost $plannedPost): void
    {
        if ($plannedPost->platformAccount === null) {
            throw new RuntimeException('Planned post is not attached to a platform account.');
        }

        if (! $plannedPost->platformAccount->is_enabled) {
            throw new RuntimeException('Platform account is disabled.');
        }

        if ($plannedPost->moderation_status !== ModerationStatus::Approved) {
            throw new RuntimeException('Only approved planned posts can be published.');
        }

        if (in_array($plannedPost->status, [PlannedPostStatus::Published, PlannedPostStatus::Cancelled, PlannedPostStatus::Replaced], true)) {
            throw new RuntimeException('This planned post cannot be published in its current state.');
        }
    }

    private function resolveDriver(PlannedPost $plannedPost): PublisherDriverInterface
    {
        if ($plannedPost->platformAccount === null) {
            throw new RuntimeException('Planned post is not attached to a platform account.');
        }

        return $this->publisherDriverResolver->resolveForPlatformAccount($plannedPost->platformAccount);
    }
}
