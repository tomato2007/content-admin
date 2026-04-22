<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PlannedPosts\Application\Actions\Concerns\InteractsWithPlannedPostWorkflow;
use App\Models\PlannedPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ApprovePlannedPostAction
{
    use InteractsWithPlannedPostWorkflow;

    public function execute(PlannedPost $plannedPost, User $actor): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canApprove(), 'This planned post cannot be approved in its current state.');

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
}
