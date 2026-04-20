<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Filament\Resources\PostingHistoryResource;
use App\Filament\Resources\PostingPlanResource;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\PostingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingPlanAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_edit_posting_plan_but_viewer_cannot(): void
    {
        [$account, $plan] = $this->makeAccountWithPlan();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $viewer = User::factory()->create();

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);
        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);
        $account->users()->attach($viewer, ['role' => PlatformAccountRole::Viewer->value]);

        $this->actingAs($owner)
            ->get(PostingPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(PostingPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(PostingPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertForbidden();
    }

    public function test_related_user_can_view_history_entry_and_stranger_cannot(): void
    {
        [$account] = $this->makeAccountWithPlan();
        $admin = User::factory()->create();
        $stranger = User::factory()->create();

        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);

        $history = PostingHistory::query()->create([
            'platform_account_id' => $account->getKey(),
            'status' => 'sent',
            'scheduled_at' => CarbonImmutable::parse('2026-04-10 10:00:00'),
            'sent_at' => CarbonImmutable::parse('2026-04-10 10:05:00'),
            'provider_message_id' => 'msg-123',
            'payload' => ['text' => 'hello'],
            'response' => ['ok' => true],
            'error' => null,
        ]);

        $this->actingAs($admin)
            ->get(PostingPlanResource::getUrl('view', ['record' => $account->postingPlan]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(PostingHistoryResource::getUrl('view', ['record' => $history]))
            ->assertOk()
            ->assertSee('msg-123');

        $this->actingAs($stranger)
            ->get(PostingHistoryResource::getUrl('view', ['record' => $history]))
            ->assertForbidden();
    }

    /**
     * @return array{0: PlatformAccount, 1: PostingPlan}
     */
    private function makeAccountWithPlan(): array
    {
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

        /** @var PostingPlan $plan */
        $plan = $account->postingPlan()->firstOrFail();

        return [$account, $plan];
    }
}
