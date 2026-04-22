<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\PlatformAccount;
use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\Contracts\PublisherDriverResolverInterface;

class PlatformAwarePublisherDriverResolver implements PublisherDriverResolverInterface
{
    public function __construct(
        private readonly TelegramPublisherDriver $telegramPublisherDriver,
        private readonly VkPublisherDriver $vkPublisherDriver,
        private readonly XPublisherDriver $xPublisherDriver,
        private readonly NullPublisherDriver $nullPublisherDriver,
    ) {}

    public function resolveForPlatformAccount(PlatformAccount $platformAccount): PublisherDriverInterface
    {
        $platformAccount->loadMissing('platform');

        return match ($platformAccount->platform?->driver) {
            'telegram' => config('services.telegram.publisher_driver') === 'real'
                ? $this->telegramPublisherDriver
                : $this->nullPublisherDriver,
            'vk' => $this->vkPublisherDriver,
            'x' => $this->xPublisherDriver,
            default => $this->nullPublisherDriver,
        };
    }
}
