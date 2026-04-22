<?php

declare(strict_types=1);

namespace App\Services\Publishing\Contracts;

use App\Models\PlatformAccount;

interface PublisherDriverResolverInterface
{
    public function resolveForPlatformAccount(PlatformAccount $platformAccount): PublisherDriverInterface;
}
