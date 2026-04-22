<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Filament\Resources\PlatformAccountResource;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_platform_access_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_local_dev_admin_can_access_admin_panel_without_platform_accounts(): void
    {
        config()->set('app.env', 'testing');

        $user = User::factory()->create([
            'email' => env('LOCAL_DEV_ADMIN_EMAIL', 'admin@local.test'),
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_user_sees_only_their_platform_accounts_on_index(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $ownedAccount = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Owned channel',
            'external_id' => 'owned-channel',
            'handle' => '@owned_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);
        $foreignAccount = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Foreign channel',
            'external_id' => 'foreign-channel',
            'handle' => '@foreign_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $ownedAccount->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);
        $foreignAccount->users()->attach($stranger, ['role' => PlatformAccountRole::Owner->value]);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($owner)
            ->get(PlatformAccountResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Owned channel')
            ->assertDontSee('Foreign channel');
    }

    public function test_related_user_can_view_platform_account_and_stranger_gets_403(): void
    {
        $viewer = User::factory()->create();
        $stranger = User::factory()->create();
        $account = $this->makePlatformAccount();

        $account->users()->attach($viewer, ['role' => PlatformAccountRole::Viewer->value]);

        $this->actingAs($viewer)
            ->get(PlatformAccountResource::getUrl('view', ['record' => $account]))
            ->assertOk()
            ->assertSee($account->title);

        $this->actingAs($stranger)
            ->get(PlatformAccountResource::getUrl('view', ['record' => $account]))
            ->assertForbidden();
    }

    public function test_only_owner_can_edit_platform_account(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $viewer = User::factory()->create();
        $account = $this->makePlatformAccount();

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);
        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);
        $account->users()->attach($viewer, ['role' => PlatformAccountRole::Viewer->value]);

        $this->actingAs($owner)
            ->get(PlatformAccountResource::getUrl('edit', ['record' => $account]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(PlatformAccountResource::getUrl('edit', ['record' => $account]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(PlatformAccountResource::getUrl('edit', ['record' => $account]))
            ->assertForbidden();
    }

    private function makePlatformAccount(): PlatformAccount
    {
        $platform = Platform::query()->create([
            'key' => 'x',
            'name' => 'X',
            'driver' => 'x',
            'is_enabled' => true,
        ]);

        return PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Test account',
            'external_id' => 'test-account',
            'handle' => '@test_account',
            'is_enabled' => true,
            'settings' => [],
        ]);
    }
}
