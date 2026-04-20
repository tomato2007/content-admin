<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostingSlot;
use App\Models\User;

class PostingSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostingSlot $postingSlot): bool
    {
        return $user->hasAccessToPlatformAccount($postingSlot->postingPlan->platformAccount);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PostingSlot $postingSlot): bool
    {
        return $user->canManagePostingPlan($postingSlot->postingPlan->platformAccount);
    }

    public function delete(User $user, PostingSlot $postingSlot): bool
    {
        return $user->canManagePostingPlan($postingSlot->postingPlan->platformAccount);
    }
}
