<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_model_returns_a_clean_not_found_message(): void
    {
        $response = $this->get(route('api.v1.products.show', 999));

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Product not found.']);
    }

    public function test_unknown_endpoint_returns_json_not_found(): void
    {
        $response = $this->get('/api/v1/unknown');

        $response->assertNotFound()
            ->assertExactJson(['message' => 'The requested endpoint does not exist.']);
    }

    public function test_guest_without_accept_header_gets_json_unauthorized(): void
    {
        $response = $this->post(route('api.v1.products.store'));

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_validation_errors_without_accept_header_are_json(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post(route('api.v1.products.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name', 'price', 'currency_id']]);
    }

    public function test_unsupported_method_returns_json(): void
    {
        $product = Product::factory()->create();

        $response = $this->post(route('api.v1.products.show', $product));

        $response->assertMethodNotAllowed()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }
}
