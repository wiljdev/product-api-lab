<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fake sleeps so the failed login timebox does not slow the suite down;
     * tests can still assert on them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

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
