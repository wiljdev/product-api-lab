<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_a_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'evaluator@example.com']);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'password',
            'device_name' => 'postman',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']]]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'postman',
        ]);
    }

    public function test_issued_token_expires_after_the_configured_minutes(): void
    {
        config(['sanctum.expiration' => 60]);
        $this->freezeSecond();
        User::factory()->create(['email' => 'evaluator@example.com']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertTrue(PersonalAccessToken::sole()->expires_at->equalTo(now()->addMinutes(60)));
    }

    public function test_issued_token_grants_access_to_protected_routes(): void
    {
        User::factory()->create(['email' => 'evaluator@example.com']);
        $token = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'evaluator@example.com');
    }

    public function test_wrong_password_and_unknown_email_get_the_same_error(): void
    {
        User::factory()->create(['email' => 'evaluator@example.com']);

        $wrongPassword = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'wrong-password',
        ]);
        $unknownEmail = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $wrongPassword->assertUnprocessable()->assertJsonValidationErrors('email');
        $unknownEmail->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson(route('api.v1.auth.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_log_out_and_the_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseEmpty('personal_access_tokens');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson(route('api.v1.auth.me'))
            ->assertUnauthorized();
    }

    public function test_logout_only_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($currentToken)
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertSame(['other'], $user->tokens()->pluck('name')->all());
    }

    public function test_guest_cannot_log_out(): void
    {
        $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token')
            ->getJson(route('api.v1.auth.me'))
            ->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'], now()->addMinutes(5))->plainTextToken;

        $this->travel(6)->minutes();

        $this->withToken($token)
            ->getJson(route('api.v1.auth.me'))
            ->assertUnauthorized();
    }
}
