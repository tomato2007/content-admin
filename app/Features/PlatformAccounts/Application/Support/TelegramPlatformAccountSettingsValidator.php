<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Support;

use App\Features\PlatformAccounts\Application\Support\Contracts\PlatformAccountSettingsValidatorInterface;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TelegramPlatformAccountSettingsValidator implements PlatformAccountSettingsValidatorInterface
{
    public function validate(PlatformAccount $platformAccount): void
    {
        $validator = Validator::make([
            'channel_key' => $platformAccount->settings['channel_key'] ?? null,
            'target_chat_id' => $platformAccount->settings['target_chat_id'] ?? null,
            'source_chat' => $platformAccount->settings['source_chat'] ?? null,
            'force_publish' => $platformAccount->settings['force_publish'] ?? false,
        ], [
            'channel_key' => ['nullable', 'string', 'max:255'],
            'target_chat_id' => ['nullable', 'string', 'max:255'],
            'source_chat' => ['nullable', 'string', 'max:255'],
            'force_publish' => ['boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
