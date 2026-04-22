<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use App\Services\Publishing\Contracts\PublishingConfigValidatorInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TelegramConfigValidator implements PublishingConfigValidatorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function validate(PlatformAccount $account): array
    {
        $platform = $account->platform;
        if ($platform === null || $platform->driver !== 'telegram') {
            throw new RuntimeException('Platform account is not configured for Telegram.');
        }

        if ($account->hasConnectedTelegramBot()) {
            return $this->validateDirectBotPublishing($account);
        }

        $channelKey = (string) ($account->settings['channel_key'] ?? '');
        if ($channelKey === '') {
            throw new RuntimeException('Missing settings.channel_key');
        }

        $connectionName = $this->runtimeConnectionName();
        $table = $this->channelConfigTable();

        $config = DB::connection($connectionName)
            ->table($table)
            ->select([
                'channel_key',
                'title',
                'active',
                'publish_mode',
                'quiet_hours_start',
                'quiet_hours_end',
                'style_mode',
            ])
            ->where('channel_key', $channelKey)
            ->first();

        if ($config === null) {
            throw new RuntimeException(sprintf('Telegram runtime config row not found in [%s] for channel_key=%s', $table, $channelKey));
        }

        return [
            'driver' => 'telegram',
            'channel_key' => $config->channel_key,
            'target' => $account->settings['target_chat_id'] ?? $config->title ?? null,
            'active' => (bool) ($config->active ?? false),
            'publish_mode' => (string) ($config->publish_mode ?? ''),
            'quiet_hours_start' => (string) ($config->quiet_hours_start ?? ''),
            'quiet_hours_end' => (string) ($config->quiet_hours_end ?? ''),
            'style_mode' => (string) ($config->style_mode ?? ''),
            'credentials_ref' => $account->credentials_ref,
            'force_publish' => (bool) ($account->settings['force_publish'] ?? false),
            'summary_lines' => [
                'Driver: Telegram',
                'Channel key: '.$config->channel_key,
                'Target: '.($account->settings['target_chat_id'] ?? $config->title ?? '—'),
                'Publish mode: '.((string) ($config->publish_mode ?? '—')),
                'Style mode: '.((string) ($config->style_mode ?? '—')),
                'Quiet hours: '.((string) ($config->quiet_hours_start ?? '—')).' → '.((string) ($config->quiet_hours_end ?? '—')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDirectBotPublishing(PlatformAccount $account): array
    {
        $targetChatId = trim((string) ($account->settings['target_chat_id'] ?? ''));

        if ($targetChatId === '') {
            throw new RuntimeException('Missing settings.target_chat_id for direct Telegram bot publishing.');
        }

        return [
            'driver' => 'telegram',
            'mode' => 'telegram_bot_api',
            'target' => $targetChatId,
            'active' => true,
            'publish_mode' => 'bot_api',
            'quiet_hours_start' => '',
            'quiet_hours_end' => '',
            'style_mode' => '',
            'credentials_ref' => $account->credentials_ref,
            'force_publish' => (bool) ($account->settings['force_publish'] ?? false),
            'bot_username' => $account->telegram_bot_username,
            'bot_name' => $account->telegram_bot_name,
            'summary_lines' => [
                'Driver: Telegram',
                'Mode: Direct bot API',
                'Bot: '.$account->telegramBotDisplayName(),
                'Target: '.$targetChatId,
                'Credentials ref: '.($account->credentials_ref ?: '—'),
            ],
        ];
    }

    private function runtimeConnectionName(): string
    {
        $connectionName = (string) config('services.telegram.runtime_connection', 'telegram_runtime');

        if (! is_array(config(sprintf('database.connections.%s', $connectionName)))) {
            throw new RuntimeException(sprintf('Telegram runtime database connection [%s] is not configured.', $connectionName));
        }

        return $connectionName;
    }

    private function channelConfigTable(): string
    {
        $table = (string) config('services.telegram.channel_config_table', 'telegram_channel_configs');

        if ($table === '') {
            throw new RuntimeException('Telegram channel config table is not configured.');
        }

        return $table;
    }
}
