<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Actions;

use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\Publishing\TelegramBotApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ConnectTelegramBotAction
{
    public function __construct(
        private readonly TelegramBotApiClient $telegramBotApiClient,
    ) {}

    public function execute(PlatformAccount $platformAccount, string $botToken, User $actor): PlatformAccount
    {
        $platformAccount->loadMissing('platform');

        if ($platformAccount->platform?->driver !== 'telegram') {
            throw new RuntimeException('Telegram bot can only be connected to Telegram platform accounts.');
        }

        if (! $actor->can('update', $platformAccount)) {
            throw new RuntimeException('You are not allowed to connect a Telegram bot to this platform account.');
        }

        $botToken = trim($botToken);

        if ($botToken === '') {
            throw new RuntimeException('Telegram bot token is required.');
        }

        $bot = $this->telegramBotApiClient->getMe($botToken);

        return DB::transaction(function () use ($platformAccount, $botToken, $bot, $actor): PlatformAccount {
            $before = $platformAccount->auditSnapshot();
            $credentialsRef = $platformAccount->credentials_ref;

            if ($credentialsRef === null || trim($credentialsRef) === '') {
                $credentialsRef = 'telegram-bot:'.($bot['username'] ?? $bot['id']);
            }

            $platformAccount->update([
                'telegram_bot_token' => $botToken,
                'telegram_bot_user_id' => $bot['id'],
                'telegram_bot_username' => $bot['username'],
                'telegram_bot_name' => $bot['name'],
                'telegram_bot_connected_at' => now(),
                'credentials_ref' => Str::limit($credentialsRef, 255, ''),
            ]);

            AdminAuditLog::logAction(
                action: 'telegram_bot_connected',
                userId: $actor->getKey(),
                platformAccountId: $platformAccount->getKey(),
                entityType: PlatformAccount::class,
                entityId: $platformAccount->getKey(),
                before: $before,
                after: $platformAccount->fresh()->auditSnapshot(),
            );

            return $platformAccount->fresh();
        });
    }
}
