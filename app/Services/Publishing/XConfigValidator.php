<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use App\Services\Publishing\Contracts\PublishingConfigValidatorInterface;
use RuntimeException;

class XConfigValidator implements PublishingConfigValidatorInterface
{
    public function validate(PlatformAccount $account): array
    {
        $platform = $account->platform;

        if ($platform === null || $platform->driver !== 'x') {
            throw new RuntimeException('Platform account is not configured for X.');
        }

        $username = $account->settings['account_username'] ?? null;
        $threadMode = $account->settings['thread_mode'] ?? 'single';

        return [
            'driver' => 'x',
            'account_username' => $username,
            'thread_mode' => $threadMode,
            'publish_mode' => 'stub',
            'force_publish' => (bool) ($account->settings['force_publish'] ?? false),
            'summary_lines' => [
                'Driver: X',
                'Username: '.($username ?? '—'),
                'Thread mode: '.$threadMode,
                'Mode: stub',
            ],
        ];
    }
}
