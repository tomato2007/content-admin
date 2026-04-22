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
        'telegram_bot_token',
        'telegram_bot_user_id',
        'telegram_bot_username',
        'telegram_bot_name',
        'telegram_bot_connected_at',
    ];

    protected $hidden = [
        'telegram_bot_token',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'telegram_bot_token' => 'encrypted',
        'telegram_bot_connected_at' => 'datetime',
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

    public function hasConnectedTelegramBot(): bool
    {
        return $this->telegram_bot_connected_at !== null
            && $this->telegram_bot_user_id !== null
            && is_string($this->telegram_bot_token)
            && $this->telegram_bot_token !== '';
    }

    public function telegramBotDisplayName(): string
    {
        if (! $this->hasConnectedTelegramBot()) {
            return 'Not connected';
        }

        $parts = array_filter([
            $this->telegram_bot_name,
            $this->telegram_bot_username ? '@'.$this->telegram_bot_username : null,
        ]);

        return $parts === [] ? 'Connected bot' : implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'id' => $this->getKey(),
            'platform_id' => $this->platform_id,
            'title' => $this->title,
            'external_id' => $this->external_id,
            'handle' => $this->handle,
            'is_enabled' => $this->is_enabled,
            'settings' => $this->settings,
            'credentials_ref' => $this->credentials_ref,
            'telegram_bot_user_id' => $this->telegram_bot_user_id,
            'telegram_bot_username' => $this->telegram_bot_username,
            'telegram_bot_name' => $this->telegram_bot_name,
            'telegram_bot_connected_at' => $this->telegram_bot_connected_at?->toDateTimeString(),
        ];
    }
}
