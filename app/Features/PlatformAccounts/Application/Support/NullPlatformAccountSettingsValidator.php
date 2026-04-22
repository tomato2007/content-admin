<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Features\PlatformAccounts\Application\Support\Contracts\PlatformAccountSettingsValidatorInterface;
use App\Models\PlatformAccount;

class NullPlatformAccountSettingsValidator implements PlatformAccountSettingsValidatorInterface
{
    public function validate(PlatformAccount $platformAccount): void {}
}
