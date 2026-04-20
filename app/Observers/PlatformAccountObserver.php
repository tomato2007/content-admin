<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;

class PlatformAccountObserver
{
    public function created(PlatformAccount $platformAccount): void
    {
        $platformAccount->postingPlan()->firstOrCreate(
            [],
            [
                'timezone' => 'UTC',
                'quiet_hours_from' => null,
                'quiet_hours_to' => null,
                'rules' => [],
                'is_active' => true,
            ],
        );

        AdminAuditLog::logAction(
            action: 'platform_account_created',
            userId: auth()->id(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: null,
            after: $platformAccount->fresh()->toArray(),
        );
    }

    public function updated(PlatformAccount $platformAccount): void
    {
        AdminAuditLog::logAction(
            action: 'platform_account_updated',
            userId: auth()->id(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: $platformAccount->getOriginal(),
            after: $platformAccount->getChanges(),
        );
    }
}
