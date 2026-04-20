<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingPlanPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_generates_upcoming_schedule_preview_from_enabled_slots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-10 08:00:00', 'UTC'));

        $platform = Platform::query()->create([
            'key' => 'vk',
            'name' => 'VK',
            'driver' => 'vk',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'VK page',
            'external_id' => 'vk-page',
            'handle' => null,
            'is_enabled' => true,
            'settings' => [],
        ]);

        $plan = $account->postingPlan()->firstOrFail();
        $plan->update(['timezone' => 'UTC']);
        $plan->postingSlots()->createMany([
            ['weekday' => 5, 'time_local' => '09:30:00', 'is_enabled' => true],
            ['weekday' => 1, 'time_local' => '12:00:00', 'is_enabled' => true],
            ['weekday' => 2, 'time_local' => '15:00:00', 'is_enabled' => false],
        ]);

        $upcoming = $plan->upcomingSlots(3);

        $this->assertCount(3, $upcoming);
        $this->assertSame('Fri, 10 Apr 2026 09:30', $upcoming[0]);
        $this->assertSame('Mon, 13 Apr 2026 12:00', $upcoming[1]);
        $this->assertSame('Fri, 17 Apr 2026 09:30', $upcoming[2]);
        $this->assertStringContainsString('Fri, 10 Apr 2026 09:30', $plan->upcomingSlotsPreview());

        CarbonImmutable::setTestNow();
    }
}
