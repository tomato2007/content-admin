<?php

declare(strict_types=1);

namespace App\Features\PlannedPosts\Application\Actions\Concerns;

use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\User;
use RuntimeException;

trait InteractsWithPlannedPostWorkflow
{
    protected function guardCanManage(PlannedPost $plannedPost, User $actor): void
    {
        $plannedPost->loadMissing('platformAccount');

        if ($plannedPost->platformAccount === null) {
            throw new RuntimeException('Planned post is not attached to a platform account.');
        }

        if (! $actor->canManagePostingPlan($plannedPost->platformAccount)) {
            throw new RuntimeException('You are not allowed to manage this planned post.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function log(string $action, PlannedPost $plannedPost, User $actor, ?array $before, ?array $after): void
    {
        AdminAuditLog::logAction(
            action: $action,
            userId: $actor->getKey(),
            platformAccountId: $plannedPost->platform_account_id,
            entityType: PlannedPost::class,
            entityId: $plannedPost->getKey(),
            before: $before,
            after: $after,
        );
    }

    protected function mergeNotes(?string $current, ?string $new): ?string
    {
        $new = trim((string) $new);

        if ($new === '') {
            return $current;
        }

        $current = trim((string) $current);

        return $current === '' ? $new : $current."\n\n".$new;
    }

    protected function guardTransition(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new RuntimeException($message);
        }
    }
}
