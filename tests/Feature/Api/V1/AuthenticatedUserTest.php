<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_authenticated_user(): void
    {
        $response = $this->getJson(route('api.v1.user'));

        $response->assertUnauthorized();
    }

    public function test_token_holder_can_access_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.v1.user'));

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $response = $this->withToken('invalid-token')->getJson(route('api.v1.user'));

        $response->assertUnauthorized();
    }
}
