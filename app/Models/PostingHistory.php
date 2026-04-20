<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostingHistoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingHistory extends Model
{
    use HasFactory;

    protected $table = 'posting_history';

    protected $fillable = [
        'platform_account_id',
        'planned_post_id',
        'status',
        'attempt_type',
        'scheduled_at',
        'sent_at',
        'provider_message_id',
        'triggered_by',
        'idempotency_key',
        'payload',
        'response',
        'error',
    ];

    protected $casts = [
        'status' => PostingHistoryStatus::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'payload' => 'array',
        'response' => 'array',
    ];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function plannedPost(): BelongsTo
    {
        return $this->belongsTo(PlannedPost::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
