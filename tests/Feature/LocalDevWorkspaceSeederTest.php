<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\User;
use Database\Seeders\LocalDevAdminSeeder;
use Database\Seeders\LocalDevWorkspaceSeeder;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalDevWorkspaceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_dev_workspace_seeder_creates_reproducible_demo_workspace(): void
    {
        $this->seed(PlatformSeeder::class);
        $this->seed(LocalDevAdminSeeder::class);
        $this->seed(LocalDevWorkspaceSeeder::class);
        $this->seed(LocalDevWorkspaceSeeder::class);

        $admin = User::query()
            ->where('email', env('LOCAL_DEV_ADMIN_EMAIL', 'admin@local.test'))
            ->first();

        $this->assertNotNull($admin);

        $account = PlatformAccount::query()
            ->where('external_id', 'content-admin-demo-channel')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Demo Telegram Channel', $account->title);
        $this->assertTrue($account->is_enabled);
        $this->assertSame('content-admin-demo-channel', $account->settings['channel_key'] ?? null);
        $this->assertSame(
            PlatformAccountRole::Owner,
            $admin->roleForPlatformAccount($account),
        );

        $plan = $account->postingPlan;

        $this->assertNotNull($plan);
        $this->assertSame('Europe/Budapest', $plan->timezone);
        $this->assertTrue($plan->is_active);
        $this->assertSame(3, $plan->postingSlots()->where('is_enabled', true)->count());

        $this->assertSame(4, $account->plannedPosts()->count());
        $this->assertSame(2, PostingHistory::query()->where('platform_account_id', $account->getKey())->count());
    }
}
