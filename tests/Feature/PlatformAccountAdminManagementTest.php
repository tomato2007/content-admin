<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccountAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_manage_account_administrators(): void
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
            'external_id' => 'tg-channel',
            'handle' => '@tg_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $viewer = User::factory()->create();

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);
        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);
        $account->users()->attach($viewer, ['role' => PlatformAccountRole::Viewer->value]);

        $this->assertTrue($owner->canManageAdministrators($account));
        $this->assertFalse($admin->canManageAdministrators($account));
        $this->assertFalse($viewer->canManageAdministrators($account));
    }
}
