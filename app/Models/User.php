<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformAccountRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function platformAccounts(): BelongsToMany
    {
        return $this->belongsToMany(PlatformAccount::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function hasAccessToPlatformAccount(PlatformAccount $platformAccount): bool
    {
        return $this->roleForPlatformAccount($platformAccount) !== null;
    }

    public function isOwnerOfPlatformAccount(PlatformAccount $platformAccount): bool
    {
        return $this->roleForPlatformAccount($platformAccount) === PlatformAccountRole::Owner;
    }

    public function canManagePostingPlan(PlatformAccount $platformAccount): bool
    {
        return in_array(
            $this->roleForPlatformAccount($platformAccount),
            [PlatformAccountRole::Owner, PlatformAccountRole::Admin],
            true,
        );
    }

    public function canManageAdministrators(PlatformAccount $platformAccount): bool
    {
        return $this->isOwnerOfPlatformAccount($platformAccount);
    }

    public function roleForPlatformAccount(PlatformAccount $platformAccount): ?PlatformAccountRole
    {
        $role = $this->platformAccounts()
            ->whereKey($platformAccount->getKey())
            ->first()?->pivot?->role;

        return $role !== null ? PlatformAccountRole::from($role) : null;
    }
}
