<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsReplacement = 'needs_replacement';
    case DeleteRequested = 'delete_requested';
    case DeleteConfirmed = 'delete_confirmed';

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
