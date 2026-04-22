<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocalDevAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('LOCAL_DEV_ADMIN_EMAIL', 'admin@local.test');
        $password = (string) env('LOCAL_DEV_ADMIN_PASSWORD', 'admin12345');
        $name = (string) env('LOCAL_DEV_ADMIN_NAME', 'Local Admin');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => $password,
                'remember_token' => Str::random(10),
            ],
        );
    }
}
