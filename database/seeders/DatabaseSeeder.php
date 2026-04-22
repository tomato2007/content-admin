<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            PlatformSeeder::class,
        ];

        if (app()->environment('local')) {
            $seeders[] = LocalDevAdminSeeder::class;
            $seeders[] = LocalDevWorkspaceSeeder::class;
        }

        $this->call($seeders);
    }
}
