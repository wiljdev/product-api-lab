<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create or refresh the demo user configured in config/demo.php.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('demo.user.email')],
            [
                'name' => config('demo.user.name'),
                'password' => config('demo.user.password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
