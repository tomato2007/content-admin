<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use App\Services\Publishing\Contracts\PublishingConfigValidatorInterface;
use RuntimeException;

class PlatformAwarePublishingConfigValidator
{
    public function __construct(
        private readonly TelegramConfigValidator $telegramConfigValidator,
        private readonly VkConfigValidator $vkConfigValidator,
        private readonly XConfigValidator $xConfigValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(PlatformAccount $account): array
    {
        return $this->resolve($account)->validate($account);
    }

    private function resolve(PlatformAccount $account): PublishingConfigValidatorInterface
    {
        $account->loadMissing('platform');

        return match ($account->platform?->driver) {
            'telegram' => $this->telegramConfigValidator,
            'vk' => $this->vkConfigValidator,
            'x' => $this->xConfigValidator,
            default => throw new RuntimeException('Platform account publishing config validator is not available for this platform.'),
        };
    }
}
