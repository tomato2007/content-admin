<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Actions;

use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;
use App\Models\User;
use RuntimeException;

class DetachPlatformAccountAdministratorAction
{
    public function execute(PlatformAccount $platformAccount, User $user, User $actor): void
    {
        $this->guard($platformAccount, $actor, $user);

        $role = $platformAccount->users()
            ->whereKey($user->getKey())
            ->first()?->pivot?->role;

        $platformAccount->users()->detach($user->getKey());

        AdminAuditLog::logAction(
            action: 'administrator_detached',
            userId: $actor->getKey(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: [
                'detached_user_id' => $user->getKey(),
                'detached_user_email' => $user->email,
                'role' => $role,
            ],
            after: null,
        );
    }

    private function guard(PlatformAccount $platformAccount, User $actor, User $user): void
    {
        if (! $actor->canManageAdministrators($platformAccount)) {
            throw new RuntimeException('You are not allowed to manage administrators for this platform account.');
        }

        if ($actor->is($user)) {
            throw new RuntimeException('You cannot remove yourself from this platform account.');
        }

        $isAttached = $platformAccount->users()
            ->whereKey($user->getKey())
            ->exists();

        if (! $isAttached) {
            throw new RuntimeException('User is not attached to this platform account.');
        }
    }
}
