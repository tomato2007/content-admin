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
use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\NullPublisherDriver;
use App\Services\Publishing\TelegramPublisherDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublisherDriverInterface::class, function () {
            $platformDriver = config('services.telegram.publisher_driver');

            return $platformDriver === 'real'
                ? new TelegramPublisherDriver()
                : new NullPublisherDriver();
        });
    }

    public function boot(): void
    {
        PlatformAccount::observe(PlatformAccountObserver::class);
        PostingPlan::observe(PostingPlanObserver::class);
        PostingSlot::observe(PostingSlotObserver::class);
        PlannedPost::observe(PlannedPostObserver::class);
    }
}
