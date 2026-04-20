<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TelegramConfigValidator
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

        $channelKey = (string) ($account->settings['channel_key'] ?? '');
        if ($channelKey === '') {
            throw new RuntimeException('Missing settings.channel_key');
        }

        $config = DB::connection('telegram_runtime')->selectOne(
            'SELECT channel_key, title, active, publish_mode, quiet_hours_start, quiet_hours_end, style_mode FROM telegram_channel_configs WHERE channel_key = ? LIMIT 1',
            [$channelKey],
        );

        if ($config === null) {
            throw new RuntimeException('telegram_channel_configs row not found for channel_key='.$channelKey);
        }

        return [
            'channel_key' => $config->channel_key,
            'target' => $account->settings['target_chat_id'] ?? $config->title ?? null,
            'active' => (bool) ($config->active ?? false),
            'publish_mode' => (string) ($config->publish_mode ?? ''),
            'quiet_hours_start' => (string) ($config->quiet_hours_start ?? ''),
            'quiet_hours_end' => (string) ($config->quiet_hours_end ?? ''),
            'style_mode' => (string) ($config->style_mode ?? ''),
            'credentials_ref' => $account->credentials_ref,
            'force_publish' => (bool) ($account->settings['force_publish'] ?? false),
        ];
    }
}
