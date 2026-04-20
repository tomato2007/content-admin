<?php

declare(strict_types=1);

namespace App\Enums;

enum PlannedPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Replaced = 'replaced';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $status): array {
                $carry[$status->value] = ucfirst(str_replace('_', ' ', $status->value));

                return $carry;
            },
            [],
        );
    }
}
