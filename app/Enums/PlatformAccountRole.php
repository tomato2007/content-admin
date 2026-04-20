<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformAccountRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Viewer = 'viewer';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $role): array {
                $carry[$role->value] = ucfirst($role->value);

                return $carry;
            },
            [],
        );
    }
}
