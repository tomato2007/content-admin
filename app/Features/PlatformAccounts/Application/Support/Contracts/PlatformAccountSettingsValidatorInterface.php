<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support\Contracts;

use App\Models\PlatformAccount;

interface PlatformAccountSettingsValidatorInterface
{
    public function validate(PlatformAccount $platformAccount): void;
}
