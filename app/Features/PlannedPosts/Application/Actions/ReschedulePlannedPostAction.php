<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions;

use App\Enums\PlannedPostStatus;
use App\Features\PlannedPosts\Application\Actions\Concerns\InteractsWithPlannedPostWorkflow;
use App\Models\PlannedPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReschedulePlannedPostAction
{
    use InteractsWithPlannedPostWorkflow;

    public function execute(PlannedPost $plannedPost, User $actor, string $scheduledAt, ?string $notes = null): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canReschedule(), 'This planned post cannot be rescheduled in its current state.');

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
}
