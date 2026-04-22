<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Features\PlatformAccounts\Application\Support\Contracts\PlatformAccountSettingsValidatorInterface;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VkPlatformAccountSettingsValidator implements PlatformAccountSettingsValidatorInterface
{
    public function validate(PlatformAccount $platformAccount): void
    {
        $validator = Validator::make([
            'community_id' => $platformAccount->settings['community_id'] ?? null,
            'screen_name' => $platformAccount->settings['screen_name'] ?? null,
            'force_publish' => $platformAccount->settings['force_publish'] ?? false,
        ], [
            'community_id' => ['nullable', 'regex:/^-?\d+$/'],
            'screen_name' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'force_publish' => ['boolean'],
        ], [
            'community_id.regex' => 'VK community ID must be a numeric owner/group identifier.',
            'screen_name.regex' => 'VK screen name may contain only letters, numbers, dots, dashes, and underscores.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
