<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostingPlan;
use App\Models\User;

class PostingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostingPlan $postingPlan): bool
    {
        return $user->hasAccessToPlatformAccount($postingPlan->platformAccount);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PostingPlan $postingPlan): bool
    {
        return $user->canManagePostingPlan($postingPlan->platformAccount);
    }

    public function delete(User $user, PostingPlan $postingPlan): bool
    {
        return false;
    }
}
