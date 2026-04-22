<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Features\PlatformAccounts\Application\Actions\CreatePlatformAccountAction;
use App\Filament\Resources\PlatformAccountResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlatformAccount extends CreateRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User|null $user */
        $user = auth()->user();

        return app(CreatePlatformAccountAction::class)->execute($data, $user);
    }
}
