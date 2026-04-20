<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlatformAccount;
use App\Models\User;

class PlatformAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PlatformAccount $platformAccount): bool
    {
        return $user->hasAccessToPlatformAccount($platformAccount);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PlatformAccount $platformAccount): bool
    {
        return $user->isOwnerOfPlatformAccount($platformAccount);
    }

    public function delete(User $user, PlatformAccount $platformAccount): bool
    {
        return $user->isOwnerOfPlatformAccount($platformAccount);
    }
}
