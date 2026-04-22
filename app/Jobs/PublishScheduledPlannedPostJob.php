<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Features\Publishing\Application\Actions\PublishScheduledPlannedPostAction;
use App\Models\PlannedPost;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledPlannedPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $plannedPostId,
        public readonly ?string $dispatchedAt = null,
    ) {}

    public function handle(PublishScheduledPlannedPostAction $publishScheduledPlannedPostAction): void
    {
        $plannedPost = PlannedPost::query()
            ->with('platformAccount.platform')
            ->find($this->plannedPostId);

        if (! $plannedPost instanceof PlannedPost) {
            return;
        }

        $referenceTime = $this->dispatchedAt !== null
            ? CarbonImmutable::parse($this->dispatchedAt)
            : CarbonImmutable::now();

        if (! $plannedPost->canAutoPublish($referenceTime)) {
            return;
        }

        $publishScheduledPlannedPostAction->execute($plannedPost);
    }
}
