<?php

declare(strict_types=1);

namespace App\Services\Publishing\Contracts;

use App\Models\PlatformAccount;

interface PublishingConfigValidatorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function validate(PlatformAccount $account): array;
}
