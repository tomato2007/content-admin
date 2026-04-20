<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\PostingPlan;
use App\Models\PostingSlot;
use App\Policies\AdminAuditLogPolicy;
use App\Policies\PlannedPostPolicy;
use App\Policies\PlatformAccountPolicy;
use App\Policies\PostingHistoryPolicy;
use App\Policies\PostingPlanPolicy;
use App\Policies\PostingSlotPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        PlatformAccount::class => PlatformAccountPolicy::class,
        PostingPlan::class => PostingPlanPolicy::class,
        PostingHistory::class => PostingHistoryPolicy::class,
        PostingSlot::class => PostingSlotPolicy::class,
        PlannedPost::class => PlannedPostPolicy::class,
        AdminAuditLog::class => AdminAuditLogPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
