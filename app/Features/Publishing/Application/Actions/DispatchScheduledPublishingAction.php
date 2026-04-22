<?php

declare(strict_types=1);

namespace App\Features\Publishing\Application\Actions;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Jobs\PublishScheduledPlannedPostJob;
use App\Models\PlannedPost;
use Carbon\CarbonImmutable;

class DispatchScheduledPublishingAction
{
    public function execute(CarbonImmutable $now, int $limit = 50, bool $sync = false): int
    {
        $duePostIds = PlannedPost::query()
            ->where('status', PlannedPostStatus::Scheduled)
            ->where('moderation_status', ModerationStatus::Approved)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->whereHas('platformAccount', static fn ($query) => $query->where('is_enabled', true))
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->pluck('id');

        $duePostIds->each(function (int $plannedPostId) use ($now, $sync): void {
            $job = new PublishScheduledPlannedPostJob($plannedPostId, $now->toDateTimeString());

            if ($sync) {
                dispatch_sync($job);

                return;
            }

            dispatch($job);
        });

        return $duePostIds->count();
    }
}
