<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'posting_plan_id',
        'weekday',
        'time_local',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function postingPlan(): BelongsTo
    {
        return $this->belongsTo(PostingPlan::class);
    }

    public function weekdayLabel(): string
    {
        return match ((int) $this->weekday) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }
}
