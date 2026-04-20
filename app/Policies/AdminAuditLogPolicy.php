<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminAuditLog;
use App\Models\User;

class AdminAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AdminAuditLog $adminAuditLog): bool
    {
        if ($adminAuditLog->platformAccount === null) {
            return false;
        }

        return $user->canManagePostingPlan($adminAuditLog->platformAccount);
    }
}
