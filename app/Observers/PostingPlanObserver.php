<?php

declare(strict_types=1);

namespace App\Observers;

use App\Features\PostingPlans\Application\Support\PostingPlanDataValidator;
use App\Models\AdminAuditLog;
use App\Models\PostingPlan;

class PostingPlanObserver
{
    public function saving(PostingPlan $postingPlan): void
    {
        app(PostingPlanDataValidator::class)->validate([
            'timezone' => $postingPlan->timezone,
            'quiet_hours_from' => $postingPlan->quiet_hours_from,
            'quiet_hours_to' => $postingPlan->quiet_hours_to,
        ]);
    }

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
