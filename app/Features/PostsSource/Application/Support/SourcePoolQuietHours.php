<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Support;

use Carbon\CarbonImmutable;

final class SourcePoolQuietHours
{
    public function isQuietPeriod(CarbonImmutable $nowUtc, string $start, string $end): bool
    {
        [$startHour, $startMinute] = $this->parseTime($start);
        [$endHour, $endMinute] = $this->parseTime($end);

        $minutes = ((int) $nowUtc->format('G') * 60) + (int) $nowUtc->format('i');
        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        if ($startMinutes === $endMinutes) {
            return false;
        }

        if ($startMinutes < $endMinutes) {
            return $minutes >= $startMinutes && $minutes < $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes < $endMinutes;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTime(string $value): array
    {
        [$hour, $minute] = array_pad(explode(':', $value), 2, '0');

        return [(int) $hour, (int) $minute];
    }
}
