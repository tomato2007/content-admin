<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Actions;

use App\Enums\PlatformAccountRole;
use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;
use App\Models\User;
use RuntimeException;

class AttachPlatformAccountAdministratorAction
{
    public function execute(PlatformAccount $platformAccount, User $user, PlatformAccountRole $role, User $actor): void
    {
        $this->guard($platformAccount, $actor);

        $alreadyAttached = $platformAccount->users()
            ->whereKey($user->getKey())
            ->exists();

        if ($alreadyAttached) {
            throw new RuntimeException('User is already attached to this platform account.');
        }

        $platformAccount->users()->attach($user->getKey(), [
            'role' => $role->value,
        ]);

        AdminAuditLog::logAction(
            action: 'administrator_attached',
            userId: $actor->getKey(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: null,
            after: [
                'attached_user_id' => $user->getKey(),
                'attached_user_email' => $user->email,
                'role' => $role->value,
            ],
        );
    }

    private function guard(PlatformAccount $platformAccount, User $actor): void
    {
        if (! $actor->canManageAdministrators($platformAccount)) {
            throw new RuntimeException('You are not allowed to manage administrators for this platform account.');
        }
    }
}
