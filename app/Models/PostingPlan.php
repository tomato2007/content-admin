<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'timezone',
        'quiet_hours_from',
        'quiet_hours_to',
        'rules',
        'is_active',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function postingSlots(): HasMany
    {
        return $this->hasMany(PostingSlot::class)->orderBy('weekday')->orderBy('time_local');
    }

    /**
     * @return array<int, string>
     */
    public function upcomingSlots(int $limit = 5): array
    {
        if (! $this->is_active) {
            return [];
        }

        $timezone = $this->timezone ?: 'UTC';
        $from = CarbonImmutable::now($timezone)->startOfMinute();
        $startOfDay = $from->startOfDay();
        $slots = $this->postingSlots()
            ->where('is_enabled', true)
            ->get(['weekday', 'time_local']);

        if ($slots->isEmpty()) {
            return [];
        }

        $occurrences = [];

        for ($offset = 0; $offset <= 35; $offset++) {
            $date = $startOfDay->addDays($offset);

            foreach ($slots as $slot) {
                if ((int) $slot->weekday !== $date->dayOfWeekIso) {
                    continue;
                }

                $candidate = $date->setTimeFromTimeString((string) $slot->time_local);

                if ($candidate->lessThan($from)) {
                    continue;
                }

                $occurrences[] = $candidate;
            }
        }

        usort($occurrences, static fn (CarbonImmutable $left, CarbonImmutable $right): int => $left->greaterThan($right) ? 1 : -1);

        return collect($occurrences)
            ->take($limit)
            ->map(static fn (CarbonImmutable $date): string => $date->isoFormat('ddd, DD MMM YYYY HH:mm'))
            ->all();
    }

    public function upcomingSlotsPreview(int $limit = 5): string
    {
        $slots = $this->upcomingSlots($limit);

        return $slots === []
            ? 'No active slots configured'
            : implode('<br>', $slots);
    }
}
