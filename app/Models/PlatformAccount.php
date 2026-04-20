<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformAccountRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'title',
        'external_id',
        'handle',
        'is_enabled',
        'settings',
        'credentials_ref',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function postingPlan(): HasOne
    {
        return $this->hasOne(PostingPlan::class);
    }

    public function postingHistory(): HasMany
    {
        return $this->hasMany(PostingHistory::class);
    }

    public function plannedPosts(): HasMany
    {
        return $this->hasMany(PlannedPost::class)->orderByDesc('scheduled_at')->orderByDesc('created_at');
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class);
    }

    public function administratorsCount(): int
    {
        return $this->users()->count();
    }

    public function ownerNameList(): string
    {
        $owners = $this->users()
            ->wherePivot('role', PlatformAccountRole::Owner->value)
            ->pluck('name')
            ->filter()
            ->values();

        return $owners->isEmpty() ? '—' : $owners->implode(', ');
    }
}
