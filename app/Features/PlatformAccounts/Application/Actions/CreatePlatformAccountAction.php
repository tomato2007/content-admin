<?php

declare(strict_types=1);

namespace App\Features\PlatformAccounts\Application\Actions;

use App\Enums\PlatformAccountRole;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePlatformAccountAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $actor = null): PlatformAccount
    {
        return DB::transaction(function () use ($data, $actor): PlatformAccount {
            $platformAccount = PlatformAccount::query()->create($data);

            if ($actor instanceof User) {
                $platformAccount->users()->syncWithoutDetaching([
                    $actor->getKey() => ['role' => PlatformAccountRole::Owner->value],
                ]);
            }

            return $platformAccount->fresh(['postingPlan']);
        });
    }
}
