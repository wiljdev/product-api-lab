<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_ORIGIN = 'http://localhost:5173';

    public function test_preflight_from_the_frontend_origin_is_allowed(): void
    {
        $response = $this->preflight(self::ALLOWED_ORIGIN, 'POST', 'authorization,content-type');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN)
            ->assertHeader('Access-Control-Max-Age', 3600)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsStringIgnoringCase('authorization', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_preflight_from_an_unknown_origin_is_not_allowed(): void
    {
        $this->preflight('https://evil.example', 'DELETE', 'authorization')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_requests_from_the_frontend_origin_receive_cors_headers(): void
    {
        $response = $this->getJson(route('api.v1.currencies.index'), ['Origin' => self::ALLOWED_ORIGIN]);

        $response->assertOk()->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $this->assertStringContainsString('X-RateLimit-Remaining', $response->headers->get('Access-Control-Expose-Headers'));
    }

    public function test_requests_from_an_unknown_origin_receive_no_cors_headers(): void
    {
        $this->getJson(route('api.v1.currencies.index'), ['Origin' => 'https://evil.example'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_allowed_origins_come_from_the_configuration(): void
    {
        config(['cors.allowed_origins' => ['https://demo.example.com']]);

        $this->preflight('https://demo.example.com', 'GET')
            ->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com');

        $this->assertNotSame(
            self::ALLOWED_ORIGIN,
            $this->preflight(self::ALLOWED_ORIGIN, 'GET')->headers->get('Access-Control-Allow-Origin'),
        );
    }

    private function preflight(string $origin, string $method, ?string $headers = null): TestResponse
    {
        return $this->call('OPTIONS', route('api.v1.products.index'), server: array_filter([
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $headers,
        ]));
    }
}
