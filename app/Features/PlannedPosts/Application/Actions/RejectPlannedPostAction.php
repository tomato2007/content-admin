<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PlannedPosts\Application\Actions\Concerns\InteractsWithPlannedPostWorkflow;
use App\Models\PlannedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectPlannedPostAction
{
    use InteractsWithPlannedPostWorkflow;

    public function execute(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canReject(), 'This planned post cannot be rejected in its current state.');

        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::Rejected,
                'status' => PlannedPostStatus::Cancelled,
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_rejected', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }
}
