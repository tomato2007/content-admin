<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PostingPlan extends Model
{
    use HasFactory;

    private const STRUCTURED_RULE_KEYS = [
        'content_mix',
        'max_posts_per_day',
        'approval_mode',
    ];

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

    public function planStatusSummary(): string
    {
        if (! $this->is_active) {
            return 'Inactive plan';
        }

        $enabledSlotCount = $this->enabledPostingSlots()->count();

        if ($enabledSlotCount === 0) {
            return 'Active plan without enabled slots';
        }

        return sprintf(
            'Active plan with %d enabled %s',
            $enabledSlotCount,
            Str::plural('slot', $enabledSlotCount),
        );
    }

    public function nextActiveSlotLabel(): string
    {
        if (! $this->is_active) {
            return 'Plan inactive';
        }

        return $this->upcomingSlots(1)[0] ?? 'No active slots configured';
    }

    public function weeklyCadenceSummary(): string
    {
        if (! $this->is_active) {
            return 'Plan inactive';
        }

        $slots = $this->enabledPostingSlots();

        if ($slots->isEmpty()) {
            return 'No active slots configured';
        }

        return $slots
            ->groupBy(static fn (PostingSlot $slot): int => (int) $slot->weekday)
            ->map(function (Collection $daySlots, int $weekday): string {
                $times = $daySlots
                    ->map(fn (PostingSlot $slot): string => $this->formatLocalTime($slot->time_local))
                    ->implode(', ');

                return sprintf('%s: %s', $this->weekdayShortLabel($weekday), $times);
            })
            ->implode(' | ');
    }

    public function quietHoursSummary(): string
    {
        $from = $this->formatLocalTime($this->quiet_hours_from);
        $to = $this->formatLocalTime($this->quiet_hours_to);

        if ($from === null && $to === null) {
            return 'Disabled';
        }

        if ($from === null || $to === null) {
            return 'Incomplete';
        }

        $suffix = $from > $to ? ' (overnight)' : '';

        return sprintf('%s -> %s%s', $from, $to, $suffix);
    }

    public function publishingRulesSummary(): string
    {
        $structuredRules = $this->structuredRulesFormData();
        $dailyCap = $structuredRules['rules_max_posts_per_day'];
        $approvalMode = $structuredRules['rules_approval_mode'];
        $additionalRulesCount = $this->additionalRulesCount();

        $parts = [
            $approvalMode === null
                ? 'Approval: default'
                : 'Approval: '.Str::headline($approvalMode),
            $dailyCap === null
                ? 'No daily cap'
                : sprintf('Max %d/day', $dailyCap),
        ];

        if ($additionalRulesCount > 0) {
            $parts[] = sprintf('+%d hidden rule %s', $additionalRulesCount, Str::plural('key', $additionalRulesCount));
        }

        return implode(' | ', $parts);
    }

    /**
     * @return array{
     *     rules_content_mix: array<int, string>,
     *     rules_max_posts_per_day: ?int,
     *     rules_approval_mode: ?string
     * }
     */
    public function structuredRulesFormData(): array
    {
        $rules = is_array($this->rules) ? $this->rules : [];

        $maxPostsPerDay = $rules['max_posts_per_day'] ?? null;

        return [
            'rules_content_mix' => $this->normalizeContentMix($rules['content_mix'] ?? []),
            'rules_max_posts_per_day' => is_numeric($maxPostsPerDay) && (int) $maxPostsPerDay > 0
                ? (int) $maxPostsPerDay
                : null,
            'rules_approval_mode' => is_string($rules['approval_mode'] ?? null) && trim((string) $rules['approval_mode']) !== ''
                ? (string) $rules['approval_mode']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergeStructuredRulesFromFormData(array $data): array
    {
        $rules = is_array($this->rules) ? $this->rules : [];

        foreach (self::STRUCTURED_RULE_KEYS as $key) {
            unset($rules[$key]);
        }

        $contentMix = $this->normalizeContentMix($data['rules_content_mix'] ?? []);
        $maxPostsPerDay = $data['rules_max_posts_per_day'] ?? null;
        $approvalMode = trim((string) ($data['rules_approval_mode'] ?? ''));

        if ($contentMix !== []) {
            $rules['content_mix'] = $contentMix;
        }

        if (is_numeric($maxPostsPerDay) && (int) $maxPostsPerDay > 0) {
            $rules['max_posts_per_day'] = (int) $maxPostsPerDay;
        }

        if ($approvalMode !== '') {
            $rules['approval_mode'] = $approvalMode;
        }

        return $rules;
    }

    public function contentMixSummary(): string
    {
        $contentMix = $this->structuredRulesFormData()['rules_content_mix'];

        return $contentMix === []
            ? '—'
            : implode(', ', $contentMix);
    }

    public function additionalRulesCount(): int
    {
        $rules = is_array($this->rules) ? $this->rules : [];

        return count(array_diff(array_keys($rules), self::STRUCTURED_RULE_KEYS));
    }

    /**
     * @param  array<int, mixed>|string|null  $contentMix
     * @return array<int, string>
     */
    private function normalizeContentMix(array|string|null $contentMix): array
    {
        if (is_string($contentMix)) {
            $contentMix = preg_split('/\s*,\s*/', trim($contentMix)) ?: [];
        }

        if (! is_array($contentMix)) {
            return [];
        }

        return collect($contentMix)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PostingSlot>
     */
    private function enabledPostingSlots(): Collection
    {
        if ($this->relationLoaded('postingSlots')) {
            /** @var Collection<int, PostingSlot> $slots */
            $slots = $this->getRelation('postingSlots');

            return $slots
                ->filter(static fn (PostingSlot $slot): bool => $slot->is_enabled)
                ->values();
        }

        /** @var Collection<int, PostingSlot> $slots */
        $slots = $this->postingSlots()
            ->where('is_enabled', true)
            ->get();

        return $slots;
    }

    private function formatLocalTime(?string $time): ?string
    {
        if (! is_string($time) || trim($time) === '') {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function weekdayShortLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun',
            default => 'Unknown',
        };
    }
}
