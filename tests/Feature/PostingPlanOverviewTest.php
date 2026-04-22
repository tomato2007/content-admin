<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingPlanOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_exposes_operator_friendly_overview_summaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-10 08:00:00', 'UTC'));

        $plan = $this->createPlanWithRules();

        $plan->update([
            'timezone' => 'UTC',
            'quiet_hours_from' => '23:00',
            'quiet_hours_to' => '07:00',
            'rules' => [
                'content_mix' => ['memes', 'stories'],
                'max_posts_per_day' => 3,
                'approval_mode' => 'manual',
                'channel_strategy' => 'balanced',
            ],
            'is_active' => true,
        ]);

        $plan->postingSlots()->createMany([
            ['weekday' => 1, 'time_local' => '09:30:00', 'is_enabled' => true],
            ['weekday' => 4, 'time_local' => '18:15:00', 'is_enabled' => true],
            ['weekday' => 5, 'time_local' => '12:00:00', 'is_enabled' => false],
        ]);

        $plan = $plan->fresh(['postingSlots']);

        $this->assertSame('Active plan with 2 enabled slots', $plan->planStatusSummary());
        $this->assertSame('Mon, 13 Apr 2026 09:30', $plan->nextActiveSlotLabel());
        $this->assertSame('Mon: 09:30 | Thu: 18:15', $plan->weeklyCadenceSummary());
        $this->assertSame('23:00 -> 07:00 (overnight)', $plan->quietHoursSummary());
        $this->assertSame('Approval: Manual | Max 3/day | +1 hidden rule key', $plan->publishingRulesSummary());

        CarbonImmutable::setTestNow();
    }

    public function test_inactive_plan_overview_reports_inactive_state(): void
    {
        $plan = $this->createPlanWithRules();

        $plan->update([
            'is_active' => false,
            'quiet_hours_from' => null,
            'quiet_hours_to' => null,
            'rules' => [],
        ]);

        $plan = $plan->fresh(['postingSlots']);

        $this->assertSame('Inactive plan', $plan->planStatusSummary());
        $this->assertSame('Plan inactive', $plan->nextActiveSlotLabel());
        $this->assertSame('Plan inactive', $plan->weeklyCadenceSummary());
        $this->assertSame('Disabled', $plan->quietHoursSummary());
        $this->assertSame('Approval: default | No daily cap', $plan->publishingRulesSummary());
    }

    private function createPlanWithRules()
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Telegram channel',
            'external_id' => 'telegram-channel',
            'handle' => null,
            'is_enabled' => true,
            'settings' => [],
        ]);

        return $account->postingPlan()->firstOrFail();
    }
}
