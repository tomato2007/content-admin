<?php

declare(strict_types=1);

namespace App\Observers;

use App\Features\PlatformAccounts\Application\Support\PlatformAccountDataValidator;
use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;

class PlatformAccountObserver
{
    public function saving(PlatformAccount $platformAccount): void
    {
        app(PlatformAccountDataValidator::class)->validate($platformAccount);
    }

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
            after: $platformAccount->fresh()->auditSnapshot(),
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
            before: array_intersect_key(
                $platformAccount->getOriginal(),
                array_flip(array_keys($platformAccount->auditSnapshot())),
            ),
            after: $platformAccount->fresh()->auditSnapshot(),
        );
    }
}
