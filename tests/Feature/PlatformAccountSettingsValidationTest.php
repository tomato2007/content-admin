<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlatformAccountSettingsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_account_rejects_overlong_target_chat_setting(): void
    {
        $platform = $this->makePlatform('telegram', 'Telegram', 'telegram');

        $this->expectException(ValidationException::class);

        PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Telegram account',
            'external_id' => 'tg-account',
            'handle' => '@tg_account',
            'is_enabled' => true,
            'settings' => [
                'target_chat_id' => str_repeat('a', 256),
            ],
        ]);
    }

    public function test_vk_account_rejects_non_numeric_community_id(): void
    {
        $platform = $this->makePlatform('vk', 'VK', 'vk');

        $this->expectException(ValidationException::class);

        PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'VK account',
            'external_id' => 'vk-account',
            'handle' => '@vk_account',
            'is_enabled' => true,
            'settings' => [
                'community_id' => 'community-slug',
            ],
        ]);
    }

    public function test_x_account_rejects_invalid_username_format(): void
    {
        $platform = $this->makePlatform('x', 'X', 'x');

        $this->expectException(ValidationException::class);

        PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'X account',
            'external_id' => 'x-account',
            'handle' => '@x_account',
            'is_enabled' => true,
            'settings' => [
                'account_username' => '@bad handle',
            ],
        ]);
    }

    public function test_vk_and_x_accounts_accept_valid_platform_specific_settings(): void
    {
        $vkPlatform = $this->makePlatform('vk', 'VK', 'vk');
        $xPlatform = $this->makePlatform('x', 'X', 'x');

        $vkAccount = PlatformAccount::query()->create([
            'platform_id' => $vkPlatform->getKey(),
            'title' => 'VK account',
            'external_id' => 'vk-account',
            'handle' => '@vk_account',
            'is_enabled' => true,
            'settings' => [
                'community_id' => '-123456789',
                'screen_name' => 'mems_vk',
            ],
        ]);

        $xAccount = PlatformAccount::query()->create([
            'platform_id' => $xPlatform->getKey(),
            'title' => 'X account',
            'external_id' => 'x-account',
            'handle' => '@x_account',
            'is_enabled' => true,
            'settings' => [
                'account_username' => '@mems_x',
                'thread_mode' => 'thread',
            ],
        ]);

        $this->assertSame('-123456789', $vkAccount->settings['community_id'] ?? null);
        $this->assertSame('@mems_x', $xAccount->settings['account_username'] ?? null);
    }

    private function makePlatform(string $key, string $name, string $driver): Platform
    {
        return Platform::query()->create([
            'key' => $key,
            'name' => $name,
            'driver' => $driver,
            'is_enabled' => true,
        ]);
    }
}
