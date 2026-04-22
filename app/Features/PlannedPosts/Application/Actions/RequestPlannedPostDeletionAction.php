<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions;

use App\Enums\ModerationStatus;
use App\Features\PlannedPosts\Application\Actions\Concerns\InteractsWithPlannedPostWorkflow;
use App\Models\PlannedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestPlannedPostDeletionAction
{
    use InteractsWithPlannedPostWorkflow;

    public function execute(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canRequestDelete(), 'This planned post cannot request deletion in its current state.');

        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::DeleteRequested,
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_delete_requested', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }
}
