<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PlannedPost;
use App\Models\PlatformAccount;
use App\Models\PostingPlan;
use App\Models\PostingSlot;
use App\Observers\PlannedPostObserver;
use App\Observers\PlatformAccountObserver;
use App\Observers\PostingPlanObserver;
use App\Observers\PostingSlotObserver;
use App\Services\Publishing\Contracts\PublisherDriverResolverInterface;
use App\Services\Publishing\NullPublisherDriver;
use App\Services\Publishing\PlatformAwarePublisherDriverResolver;
use App\Services\Publishing\TelegramPublisherDriver;
use App\Services\Publishing\VkPublisherDriver;
use App\Services\Publishing\XPublisherDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramPublisherDriver::class);
        $this->app->singleton(NullPublisherDriver::class);
        $this->app->singleton(VkPublisherDriver::class);
        $this->app->singleton(XPublisherDriver::class);
        $this->app->singleton(PublisherDriverResolverInterface::class, PlatformAwarePublisherDriverResolver::class);
    }

    public function boot(): void
    {
        PlatformAccount::observe(PlatformAccountObserver::class);
        PostingPlan::observe(PostingPlanObserver::class);
        PostingSlot::observe(PostingSlotObserver::class);
        PlannedPost::observe(PlannedPostObserver::class);
    }
}
