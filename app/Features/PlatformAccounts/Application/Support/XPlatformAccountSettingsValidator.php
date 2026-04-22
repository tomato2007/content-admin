<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Features\PlatformAccounts\Application\Support\Contracts\PlatformAccountSettingsValidatorInterface;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class XPlatformAccountSettingsValidator implements PlatformAccountSettingsValidatorInterface
{
    public function validate(PlatformAccount $platformAccount): void
    {
        $validator = Validator::make([
            'account_username' => $platformAccount->settings['account_username'] ?? null,
            'thread_mode' => $platformAccount->settings['thread_mode'] ?? null,
            'force_publish' => $platformAccount->settings['force_publish'] ?? false,
        ], [
            'account_username' => ['nullable', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],
            'thread_mode' => ['nullable', 'in:single,thread'],
            'force_publish' => ['boolean'],
        ], [
            'account_username.regex' => 'X username must be a valid handle up to 15 characters and may optionally start with @.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
