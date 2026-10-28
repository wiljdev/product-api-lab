<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_rate_limit_headers(): void
    {
        $this->getJson(route('api.v1.products.index'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 60)
            ->assertHeader('X-RateLimit-Remaining', 59);
    }

    public function test_api_returns_too_many_requests_after_sixty_requests_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->getJson(route('api.v1.currencies.index'))->assertOk();
        }

        $this->getJson(route('api.v1.currencies.index'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Too Many Attempts.');
    }

    public function test_api_limit_resets_after_a_minute(): void
    {
        for ($attempt = 1; $attempt <= 61; $attempt++) {
            $this->getJson(route('api.v1.currencies.index'));
        }

        $this->travel(61)->seconds();

        $this->getJson(route('api.v1.currencies.index'))->assertOk();
    }

    public function test_login_is_limited_to_five_attempts_per_minute_per_email(): void
    {
        User::factory()->create(['email' => 'evaluator@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('api.v1.auth.login'), [
                'email' => 'evaluator@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'password',
        ])->assertTooManyRequests();
    }

    public function test_login_limit_for_one_email_does_not_block_another(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('api.v1.auth.login'), [
                'email' => 'attacked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        User::factory()->create(['email' => 'evaluator@example.com']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'evaluator@example.com',
            'password' => 'password',
        ])->assertOk();
    }
}
