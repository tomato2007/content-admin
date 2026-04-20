<?php

declare(strict_types=1);

namespace App\Enums;

enum PostingHistoryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $status): array {
                $carry[$status->value] = ucfirst($status->value);

                return $carry;
            },
            [],
        );
    }
}
