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

class ConfirmPlannedPostDeletionAction
{
    use InteractsWithPlannedPostWorkflow;

    public function execute(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canConfirmDelete(), 'This planned post cannot confirm deletion in its current state.');

        return DB::transaction(function () use ($plannedPost, $actor, $notes): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::DeleteConfirmed,
                'status' => PlannedPostStatus::Cancelled,
                'delete_confirmed_by' => $actor->getKey(),
                'delete_confirmed_at' => CarbonImmutable::now(),
                'notes' => $this->mergeNotes($plannedPost->notes, $notes),
            ]);

            $this->log('planned_post_delete_confirmed', $plannedPost, $actor, $before, $plannedPost->fresh()->toArray());

            return $plannedPost->fresh();
        });
    }
}
