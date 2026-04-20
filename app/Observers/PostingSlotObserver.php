<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdminAuditLog;
use App\Models\PostingSlot;

class PostingSlotObserver
{
    public function created(PostingSlot $postingSlot): void
    {
        AdminAuditLog::logAction(
            action: 'posting_slot_created',
            userId: auth()->id(),
            platformAccountId: $postingSlot->postingPlan->platform_account_id,
            entityType: PostingSlot::class,
            entityId: $postingSlot->getKey(),
            before: null,
            after: $postingSlot->toArray(),
        );
    }

    public function updated(PostingSlot $postingSlot): void
    {
        AdminAuditLog::logAction(
            action: 'posting_slot_updated',
            userId: auth()->id(),
            platformAccountId: $postingSlot->postingPlan->platform_account_id,
            entityType: PostingSlot::class,
            entityId: $postingSlot->getKey(),
            before: $postingSlot->getOriginal(),
            after: $postingSlot->getChanges(),
        );
    }

    public function deleted(PostingSlot $postingSlot): void
    {
        AdminAuditLog::logAction(
            action: 'posting_slot_deleted',
            userId: auth()->id(),
            platformAccountId: $postingSlot->postingPlan->platform_account_id,
            entityType: PostingSlot::class,
            entityId: $postingSlot->getKey(),
            before: $postingSlot->getOriginal(),
            after: null,
        );
    }
}
