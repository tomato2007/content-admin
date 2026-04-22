<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlatformAccountDataValidator
{
    public function __construct(
        private readonly PlatformAccountSettingsValidatorResolver $platformAccountSettingsValidatorResolver,
    ) {}

    public function validate(PlatformAccount $platformAccount): void
    {
        $validator = Validator::make([
            'platform_id' => $platformAccount->platform_id,
            'title' => $platformAccount->title,
            'external_id' => $platformAccount->external_id,
            'handle' => $platformAccount->handle,
            'credentials_ref' => $platformAccount->credentials_ref,
            'settings' => $platformAccount->settings,
            'telegram_bot_user_id' => $platformAccount->telegram_bot_user_id,
            'telegram_bot_username' => $platformAccount->telegram_bot_username,
            'telegram_bot_name' => $platformAccount->telegram_bot_name,
        ], [
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'title' => ['required', 'string', 'max:255'],
            'external_id' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'credentials_ref' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'telegram_bot_user_id' => ['nullable', 'integer'],
            'telegram_bot_username' => ['nullable', 'string', 'max:255'],
            'telegram_bot_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->platformAccountSettingsValidatorResolver->resolve($platformAccount)->validate($platformAccount);
    }
}
