<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlannedPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'platform_account_id',
        'source_type',
        'source_id',
        'content_snapshot',
        'content_text',
        'scheduled_at',
        'status',
        'moderation_status',
        'replace_of_id',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'delete_confirmed_by',
        'delete_confirmed_at',
        'notes',
    ];

    protected $casts = [
        'content_snapshot' => 'array',
        'scheduled_at' => 'datetime',
        'status' => PlannedPostStatus::class,
        'moderation_status' => ModerationStatus::class,
        'approved_at' => 'datetime',
        'delete_confirmed_at' => 'datetime',
    ];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function replacementOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replace_of_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replace_of_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleteConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delete_confirmed_by');
    }

    public function contentPreview(int $limit = 80): string
    {
        $text = trim((string) $this->content_text);

        if ($text === '') {
            return '—';
        }

        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit - 1).'…'
            : $text;
    }

    public function isTerminalStatus(): bool
    {
        return in_array($this->status, [
            PlannedPostStatus::Published,
            PlannedPostStatus::Cancelled,
            PlannedPostStatus::Replaced,
        ], true);
    }

    public function canApprove(): bool
    {
        return ! $this->isTerminalStatus()
            && in_array($this->moderation_status, [
                ModerationStatus::PendingReview,
                ModerationStatus::NeedsReplacement,
            ], true);
    }

    public function canReject(): bool
    {
        return ! $this->isTerminalStatus()
            && in_array($this->moderation_status, [
                ModerationStatus::PendingReview,
                ModerationStatus::NeedsReplacement,
                ModerationStatus::Approved,
                ModerationStatus::DeleteRequested,
            ], true);
    }

    public function canRequestDelete(): bool
    {
        return ! $this->isTerminalStatus()
            && ! in_array($this->moderation_status, [
                ModerationStatus::DeleteRequested,
                ModerationStatus::DeleteConfirmed,
            ], true);
    }

    public function canConfirmDelete(): bool
    {
        return ! $this->isTerminalStatus()
            && $this->moderation_status === ModerationStatus::DeleteRequested;
    }

    public function canReplace(): bool
    {
        return ! $this->isTerminalStatus();
    }

    public function canReschedule(): bool
    {
        return ! $this->isTerminalStatus();
    }

    public function canAutoPublish(CarbonImmutable $now): bool
    {
        return $this->status === PlannedPostStatus::Scheduled
            && $this->moderation_status === ModerationStatus::Approved
            && $this->scheduled_at !== null
            && $this->scheduled_at->toImmutable()->lessThanOrEqualTo($now)
            && $this->platformAccount?->is_enabled === true;
    }
}
