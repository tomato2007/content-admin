<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Enums\PlatformAccountRole;
use App\Filament\Resources\PlatformAccountResource;
use App\Models\PlatformAccount;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePlatformAccount extends CreateRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            /** @var PlatformAccount $platformAccount */
            $platformAccount = static::getModel()::query()->create($data);

            $user = auth()->user();

            if ($user instanceof User) {
                $platformAccount->users()->syncWithoutDetaching([
                    $user->getKey() => ['role' => PlatformAccountRole::Owner->value],
                ]);
            }

            return $platformAccount->fresh(['postingPlan']);
        });
    }
}
