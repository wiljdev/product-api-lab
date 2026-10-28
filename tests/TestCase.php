<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as an editor holding the token abilities of its role.
     */
    protected function actingAsEditor(): User
    {
        $user = User::factory()->editor()->create();

        Sanctum::actingAs($user, $user->role->abilities());

        return $user;
    }
}
