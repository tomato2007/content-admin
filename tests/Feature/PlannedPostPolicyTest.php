<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedPostPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_update_planned_post_but_viewer_cannot(): void
    {
        $platform = Platform::query()->create([
            'key' => 'x',
            'name' => 'X',
            'driver' => 'x',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Queue account',
            'external_id' => 'queue-account',
            'handle' => '@queue_account',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $plannedPost = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Queue moderation item',
            'scheduled_at' => '2026-04-15 11:00:00',
        ]);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $viewer = User::factory()->create();
        $stranger = User::factory()->create();

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);
        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);
        $account->users()->attach($viewer, ['role' => PlatformAccountRole::Viewer->value]);

        $this->assertTrue($owner->can('update', $plannedPost));
        $this->assertTrue($admin->can('update', $plannedPost));
        $this->assertFalse($viewer->can('update', $plannedPost));
        $this->assertFalse($stranger->can('view', $plannedPost));
        $this->assertTrue($viewer->can('view', $plannedPost));
    }
}
