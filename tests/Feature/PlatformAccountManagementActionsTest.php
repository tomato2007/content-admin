<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Features\PlatformAccounts\Application\Actions\AttachPlatformAccountAdministratorAction;
use App\Features\PlatformAccounts\Application\Actions\ChangePlatformAccountAdministratorRoleAction;
use App\Features\PlatformAccounts\Application\Actions\CreatePlatformAccountAction;
use App\Features\PlatformAccounts\Application\Actions\DetachPlatformAccountAdministratorAction;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccountManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_platform_account_action_assigns_owner_and_creates_plan(): void
    {
        $actor = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = app(CreatePlatformAccountAction::class)->execute([
            'platform_id' => $platform->getKey(),
            'title' => 'Telegram channel',
            'external_id' => 'tg-channel',
            'handle' => '@tg_channel',
            'is_enabled' => true,
            'settings' => [],
        ], $actor);

        $this->assertInstanceOf(PlatformAccount::class, $account);
        $this->assertSame(PlatformAccountRole::Owner, $actor->roleForPlatformAccount($account));
        $this->assertNotNull($account->postingPlan);
    }

    public function test_administrator_management_actions_update_membership_and_audit(): void
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Telegram channel',
            'external_id' => 'tg-channel',
            'handle' => '@tg_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);

        app(AttachPlatformAccountAdministratorAction::class)->execute(
            $account,
            $admin,
            PlatformAccountRole::Admin,
            $owner,
        );

        $this->assertSame(PlatformAccountRole::Admin, $admin->roleForPlatformAccount($account));

        app(ChangePlatformAccountAdministratorRoleAction::class)->execute(
            $account,
            $admin,
            PlatformAccountRole::Viewer,
            $owner,
        );

        $this->assertSame(PlatformAccountRole::Viewer, $admin->fresh()->roleForPlatformAccount($account));

        app(DetachPlatformAccountAdministratorAction::class)->execute(
            $account,
            $admin->fresh(),
            $owner,
        );

        $this->assertNull($admin->fresh()->roleForPlatformAccount($account));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'administrator_attached',
            'platform_account_id' => $account->getKey(),
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'administrator_role_changed',
            'platform_account_id' => $account->getKey(),
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'administrator_detached',
            'platform_account_id' => $account->getKey(),
        ]);
    }
}
