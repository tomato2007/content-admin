<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingPlanRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_plan_structured_rules_form_data_parses_legacy_rule_storage(): void
    {
        $plan = $this->makePlan();

        $plan->update([
            'rules' => [
                'content_mix' => 'memes, stories, reposts',
                'max_posts_per_day' => '3',
                'approval_mode' => 'manual',
                'legacy_flag' => 'keep-me',
            ],
        ]);

        $structured = $plan->fresh()->structuredRulesFormData();

        $this->assertSame(['memes', 'stories', 'reposts'], $structured['rules_content_mix']);
        $this->assertSame(3, $structured['rules_max_posts_per_day']);
        $this->assertSame('manual', $structured['rules_approval_mode']);
        $this->assertSame('memes, stories, reposts', $plan->fresh()->contentMixSummary());
        $this->assertSame(1, $plan->fresh()->additionalRulesCount());
    }

    public function test_posting_plan_merge_structured_rules_preserves_unknown_rule_keys(): void
    {
        $plan = $this->makePlan();

        $plan->update([
            'rules' => [
                'content_mix' => 'memes, stories',
                'max_posts_per_day' => '2',
                'approval_mode' => 'manual',
                'legacy_flag' => 'keep-me',
            ],
        ]);

        $mergedRules = $plan->fresh()->mergeStructuredRulesFromFormData([
            'rules_content_mix' => ['stories', 'reposts'],
            'rules_max_posts_per_day' => '4',
            'rules_approval_mode' => 'assisted',
        ]);

        $this->assertSame(['stories', 'reposts'], $mergedRules['content_mix']);
        $this->assertSame(4, $mergedRules['max_posts_per_day']);
        $this->assertSame('assisted', $mergedRules['approval_mode']);
        $this->assertSame('keep-me', $mergedRules['legacy_flag']);
    }

    private function makePlan(): PostingPlan
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Rules channel',
            'external_id' => 'rules-channel',
            'handle' => '@rules_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        return $account->postingPlan()->firstOrFail();
    }
}
