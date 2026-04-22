<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformAccountRole;
use App\Features\PlatformAccounts\Application\Actions\ConnectTelegramBotAction;
use App\Models\AdminAuditLog;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TelegramBotConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_telegram_bot_action_validates_token_and_stores_encrypted_binding(): void
    {
        [$owner, $account] = $this->makeOwnerAndTelegramAccount();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Content Admin',
                    'username' => 'content_admin_bot',
                ],
            ]),
        ]);

        $connected = app(ConnectTelegramBotAction::class)->execute(
            $account,
            '123456:ABCDEF',
            $owner,
        );

        $this->assertTrue($connected->hasConnectedTelegramBot());
        $this->assertSame(123456789, $connected->telegram_bot_user_id);
        $this->assertSame('content_admin_bot', $connected->telegram_bot_username);
        $this->assertSame('Content Admin', $connected->telegram_bot_name);
        $this->assertSame('123456:ABCDEF', $connected->telegram_bot_token);
        $this->assertSame('telegram-bot:content_admin_bot', $connected->credentials_ref);
        $this->assertNotNull($connected->telegram_bot_connected_at);

        $storedToken = DB::table('platform_accounts')
            ->where('id', $account->getKey())
            ->value('telegram_bot_token');

        $this->assertIsString($storedToken);
        $this->assertNotSame('123456:ABCDEF', $storedToken);

        $auditLog = AdminAuditLog::query()
            ->where('action', 'telegram_bot_connected')
            ->where('entity_id', (string) $account->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($owner->getKey(), $auditLog->user_id);
        $this->assertSame('content_admin_bot', $auditLog->after['telegram_bot_username'] ?? null);
        $this->assertArrayNotHasKey('telegram_bot_token', $auditLog->after ?? []);
    }

    public function test_connect_telegram_bot_action_rejects_invalid_token(): void
    {
        [$owner, $account] = $this->makeOwnerAndTelegramAccount();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');

        app(ConnectTelegramBotAction::class)->execute(
            $account,
            'invalid-token',
            $owner,
        );
    }

    public function test_only_owner_can_connect_telegram_bot(): void
    {
        [$owner, $account] = $this->makeOwnerAndTelegramAccount();
        $admin = User::factory()->create();
        $account->users()->attach($admin, ['role' => PlatformAccountRole::Admin->value]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Content Admin',
                    'username' => 'content_admin_bot',
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You are not allowed to connect a Telegram bot to this platform account.');

        app(ConnectTelegramBotAction::class)->execute(
            $account,
            '123456:ABCDEF',
            $admin,
        );
    }

    /**
     * @return array{0: User, 1: PlatformAccount}
     */
    private function makeOwnerAndTelegramAccount(): array
    {
        $owner = User::factory()->create();
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

        $account->users()->attach($owner, ['role' => PlatformAccountRole::Owner->value]);

        return [$owner, $account];
    }
}
