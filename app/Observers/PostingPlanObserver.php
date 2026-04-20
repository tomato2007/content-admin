<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminAuditLog;
use App\Models\PostingPlan;

class PostingPlanObserver
{
    public function created(PostingPlan $postingPlan): void
    {
        AdminAuditLog::logAction(
            action: 'posting_plan_created',
            userId: auth()->id(),
            platformAccountId: $postingPlan->platform_account_id,
            entityType: PostingPlan::class,
            entityId: $postingPlan->getKey(),
            before: null,
            after: $postingPlan->toArray(),
        );
    }

    public function updated(PostingPlan $postingPlan): void
    {
        AdminAuditLog::logAction(
            action: 'posting_plan_updated',
            userId: auth()->id(),
            platformAccountId: $postingPlan->platform_account_id,
            entityType: PostingPlan::class,
            entityId: $postingPlan->getKey(),
            before: $postingPlan->getOriginal(),
            after: $postingPlan->getChanges(),
        );
    }
}
