<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use App\Services\Publishing\Contracts\PublishingConfigValidatorInterface;
use RuntimeException;

class VkConfigValidator implements PublishingConfigValidatorInterface
{
    public function validate(PlatformAccount $account): array
    {
        $platform = $account->platform;

        if ($platform === null || $platform->driver !== 'vk') {
            throw new RuntimeException('Platform account is not configured for VK.');
        }

        $communityId = $account->settings['community_id'] ?? null;
        $screenName = $account->settings['screen_name'] ?? null;

        return [
            'driver' => 'vk',
            'community_id' => $communityId,
            'screen_name' => $screenName,
            'publish_mode' => 'stub',
            'force_publish' => (bool) ($account->settings['force_publish'] ?? false),
            'summary_lines' => [
                'Driver: VK',
                'Community ID: '.($communityId ?? '—'),
                'Screen name: '.($screenName ?? '—'),
                'Mode: stub',
            ],
        ];
    }
}
