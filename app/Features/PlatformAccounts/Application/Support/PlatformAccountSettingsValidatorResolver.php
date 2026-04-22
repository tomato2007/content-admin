<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Features\PlatformAccounts\Application\Support\Contracts\PlatformAccountSettingsValidatorInterface;
use App\Models\Platform;
use App\Models\PlatformAccount;

class PlatformAccountSettingsValidatorResolver
{
    public function __construct(
        private readonly TelegramPlatformAccountSettingsValidator $telegramValidator,
        private readonly VkPlatformAccountSettingsValidator $vkValidator,
        private readonly XPlatformAccountSettingsValidator $xValidator,
        private readonly NullPlatformAccountSettingsValidator $nullValidator,
    ) {}

    public function resolve(PlatformAccount $platformAccount): PlatformAccountSettingsValidatorInterface
    {
        return match ($this->platformDriver($platformAccount)) {
            'telegram' => $this->telegramValidator,
            'vk' => $this->vkValidator,
            'x' => $this->xValidator,
            default => $this->nullValidator,
        };
    }

    private function platformDriver(PlatformAccount $platformAccount): ?string
    {
        $platform = $platformAccount->platform;

        if ($platform === null && $platformAccount->platform_id !== null) {
            $platform = Platform::query()->find($platformAccount->platform_id);

            if ($platform !== null) {
                $platformAccount->setRelation('platform', $platform);
            }
        }

        return $platform?->driver;
    }
}
