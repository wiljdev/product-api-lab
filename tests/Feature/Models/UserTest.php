<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_viewers_by_default(): void
    {
        $user = User::create([
            'name' => 'New user',
            'email' => 'new@example.com',
            'password' => 'secret-password',
        ]);

        $this->assertSame(UserRole::Viewer, $user->fresh()->role);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'secret-password',
            'role' => UserRole::Editor,
        ]);

        $this->assertSame(UserRole::Viewer, $user->fresh()->role);

        $user->update(['role' => UserRole::Editor]);

        $this->assertSame(UserRole::Viewer, $user->fresh()->role);
    }

    public function test_role_can_be_changed_explicitly(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['role' => UserRole::Editor])->save();

        $this->assertSame(UserRole::Editor, $user->fresh()->role);
    }
}
