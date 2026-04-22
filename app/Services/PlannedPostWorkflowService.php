<?php

declare(strict_types=1);

namespace App\Services;

use App\Features\PlannedPosts\Application\Actions\ApprovePlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\ConfirmPlannedPostDeletionAction;
use App\Features\PlannedPosts\Application\Actions\RejectPlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\ReplacePlannedPostAction;
use App\Features\PlannedPosts\Application\Actions\RequestPlannedPostDeletionAction;
use App\Features\PlannedPosts\Application\Actions\ReschedulePlannedPostAction;
use App\Models\PlannedPost;
use App\Models\User;

class PlannedPostWorkflowService
{
    public function __construct(
        private readonly ApprovePlannedPostAction $approvePlannedPostAction,
        private readonly RejectPlannedPostAction $rejectPlannedPostAction,
        private readonly RequestPlannedPostDeletionAction $requestPlannedPostDeletionAction,
        private readonly ConfirmPlannedPostDeletionAction $confirmPlannedPostDeletionAction,
        private readonly ReplacePlannedPostAction $replacePlannedPostAction,
        private readonly ReschedulePlannedPostAction $reschedulePlannedPostAction,
    ) {}

    public function approve(PlannedPost $plannedPost, User $actor): PlannedPost
    {
        return $this->approvePlannedPostAction->execute($plannedPost, $actor);
    }

    public function reject(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return $this->rejectPlannedPostAction->execute($plannedPost, $actor, $notes);
    }

    public function requestDelete(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return $this->requestPlannedPostDeletionAction->execute($plannedPost, $actor, $notes);
    }

    public function confirmDelete(PlannedPost $plannedPost, User $actor, ?string $notes = null): PlannedPost
    {
        return $this->confirmPlannedPostDeletionAction->execute($plannedPost, $actor, $notes);
    }

    /**
     * @param  array<string, mixed>  $replacementData
     */
    public function replace(PlannedPost $plannedPost, User $actor, array $replacementData): PlannedPost
    {
        return $this->replacePlannedPostAction->execute($plannedPost, $actor, $replacementData);
    }

    public function reschedule(PlannedPost $plannedPost, User $actor, string $scheduledAt, ?string $notes = null): PlannedPost
    {
        return $this->reschedulePlannedPostAction->execute($plannedPost, $actor, $scheduledAt, $notes);
    }
}
