<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create or refresh the demo users configured in config/demo.php.
     */
    public function run(): void
    {
        $this->seedUser(config('demo.user'), UserRole::Editor);
        $this->seedUser(config('demo.viewer'), UserRole::Viewer);
    }

    /**
     * @param  array{name: string, email: string, password: string}  $credentials
     */
    private function seedUser(array $credentials, UserRole $role): void
    {
        User::updateOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'role' => $role,
                'email_verified_at' => now(),
            ],
        );
    }
}
