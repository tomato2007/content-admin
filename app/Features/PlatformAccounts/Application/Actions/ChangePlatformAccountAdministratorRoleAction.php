<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Actions;

use App\Enums\PlatformAccountRole;
use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;
use App\Models\User;
use RuntimeException;

class ChangePlatformAccountAdministratorRoleAction
{
    public function execute(PlatformAccount $platformAccount, User $user, PlatformAccountRole $role, User $actor): void
    {
        $this->guard($platformAccount, $actor, $user);

        $currentRole = $platformAccount->users()
            ->whereKey($user->getKey())
            ->first()?->pivot?->role;

        $platformAccount->users()->updateExistingPivot($user->getKey(), [
            'role' => $role->value,
        ]);

        AdminAuditLog::logAction(
            action: 'administrator_role_changed',
            userId: $actor->getKey(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: [
                'role' => $currentRole,
            ],
            after: [
                'user_id' => $user->getKey(),
                'user_email' => $user->email,
                'role' => $role->value,
            ],
        );
    }

    private function guard(PlatformAccount $platformAccount, User $actor, User $user): void
    {
        if (! $actor->canManageAdministrators($platformAccount)) {
            throw new RuntimeException('You are not allowed to manage administrators for this platform account.');
        }

        $isAttached = $platformAccount->users()
            ->whereKey($user->getKey())
            ->exists();

        if (! $isAttached) {
            throw new RuntimeException('User is not attached to this platform account.');
        }
    }
}
