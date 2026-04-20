<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlannedPost;
use App\Models\User;

class PlannedPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PlannedPost $plannedPost): bool
    {
        return $user->hasAccessToPlatformAccount($plannedPost->platformAccount);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PlannedPost $plannedPost): bool
    {
        return $user->canManagePostingPlan($plannedPost->platformAccount);
    }

    public function delete(User $user, PlannedPost $plannedPost): bool
    {
        return $user->canManagePostingPlan($plannedPost->platformAccount);
    }
}
