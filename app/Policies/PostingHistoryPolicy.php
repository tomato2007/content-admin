<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostingHistory;
use App\Models\User;

class PostingHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostingHistory $postingHistory): bool
    {
        return $user->hasAccessToPlatformAccount($postingHistory->platformAccount);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PostingHistory $postingHistory): bool
    {
        return false;
    }

    public function delete(User $user, PostingHistory $postingHistory): bool
    {
        return false;
    }
}
