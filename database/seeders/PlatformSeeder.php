<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'telegram', 'name' => 'Telegram', 'driver' => 'telegram'],
            ['key' => 'vk', 'name' => 'VK', 'driver' => 'vk'],
            ['key' => 'x', 'name' => 'X', 'driver' => 'x'],
        ] as $platform) {
            Platform::query()->updateOrCreate(
                ['key' => $platform['key']],
                [
                    'name' => $platform['name'],
                    'driver' => $platform['driver'],
                    'is_enabled' => true,
                ],
            );
        }
    }
}
