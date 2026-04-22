<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PlannedPosts\Application\Actions\Concerns\InteractsWithPlannedPostWorkflow;
use App\Models\PlannedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReplacePlannedPostAction
{
    use InteractsWithPlannedPostWorkflow;

    /**
     * @param  array<string, mixed>  $replacementData
     */
    public function execute(PlannedPost $plannedPost, User $actor, array $replacementData): PlannedPost
    {
        $this->guardCanManage($plannedPost, $actor);
        $this->guardTransition($plannedPost->canReplace(), 'This planned post cannot be replaced in its current state.');

        return DB::transaction(function () use ($plannedPost, $actor, $replacementData): PlannedPost {
            $before = $plannedPost->fresh()->toArray();

            $plannedPost->update([
                'moderation_status' => ModerationStatus::NeedsReplacement,
                'status' => PlannedPostStatus::Replaced,
                'notes' => $this->mergeNotes($plannedPost->notes, $replacementData['reason'] ?? 'Replacement requested'),
            ]);

            $replacement = PlannedPost::query()->create([
                'platform_account_id' => $plannedPost->platform_account_id,
                'source_type' => $replacementData['source_type'] ?? $plannedPost->source_type,
                'source_id' => $replacementData['source_id'] ?? null,
                'content_text' => $replacementData['content_text'] ?? $plannedPost->content_text,
                'scheduled_at' => $replacementData['scheduled_at'] ?? $plannedPost->scheduled_at,
                'status' => ($replacementData['scheduled_at'] ?? $plannedPost->scheduled_at) !== null
                    ? PlannedPostStatus::Scheduled
                    : PlannedPostStatus::Draft,
                'moderation_status' => ModerationStatus::PendingReview,
                'replace_of_id' => $plannedPost->getKey(),
                'notes' => $replacementData['notes'] ?? null,
            ]);

            $this->log('planned_post_replaced', $plannedPost, $actor, $before, [
                'original' => $plannedPost->fresh()->toArray(),
                'replacement_id' => $replacement->getKey(),
            ]);

            return $replacement->fresh();
        });
    }
}
