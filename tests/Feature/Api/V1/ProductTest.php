<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_products_paginated(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson(route('api.v1.products.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'description', 'price', 'currency_id',
                    'currency' => ['id', 'name', 'symbol', 'exchange_rate'],
                    'tax_cost', 'manufacturing_cost', 'created_at', 'updated_at',
                ]],
                'links',
                'meta',
            ]);
    }

    public function test_per_page_is_capped(): void
    {
        $response = $this->getJson(route('api.v1.products.index', ['per_page' => 1000]));

        $response->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_guest_can_view_a_product(): void
    {
        $product = Product::factory()->create(['price' => 120.5]);

        $response = $this->getJson(route('api.v1.products.show', $product));

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', '120.50')
            ->assertJsonPath('data.currency.id', $product->currency_id);
    }

    public function test_viewing_a_missing_product_returns_not_found(): void
    {
        $this->getJson(route('api.v1.products.show', 999))->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();

        $response = $this->postJson(route('api.v1.products.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.price', '150.00')
            ->assertJsonPath('data.currency.id', $payload['currency_id']);
        $this->assertDatabaseHas('products', ['name' => $payload['name']]);
    }

    public function test_guest_cannot_create_a_product(): void
    {
        $this->postJson(route('api.v1.products.store'), $this->validPayload())
            ->assertUnauthorized();

        $this->assertDatabaseEmpty('products');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_product_creation_is_validated(array $overrides, string $invalidField): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson(route('api.v1.products.store'), [...$this->validPayload(), ...$overrides]);

        $response->assertUnprocessable()->assertJsonValidationErrors($invalidField);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'missing description' => [['description' => null], 'description'],
            'negative price' => [['price' => -1], 'price'],
            'non numeric price' => [['price' => 'abc'], 'price'],
            'price with three decimals' => [['price' => 10.123], 'price'],
            'missing currency' => [['currency_id' => null], 'currency_id'],
            'unknown currency' => [['currency_id' => 999], 'currency_id'],
            'negative tax cost' => [['tax_cost' => -0.01], 'tax_cost'],
            'missing manufacturing cost' => [['manufacturing_cost' => null], 'manufacturing_cost'],
        ];
    }

    public function test_authenticated_user_can_replace_a_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $payload = $this->validPayload();

        $response = $this->putJson(route('api.v1.products.update', $product), $payload);

        $response->assertOk()->assertJsonPath('data.name', $payload['name']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $payload['name']]);
    }

    public function test_put_requires_every_field(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $response = $this->putJson(route('api.v1.products.update', $product), ['name' => 'Only name']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'price', 'currency_id', 'tax_cost', 'manufacturing_cost']);
    }

    public function test_patch_updates_only_the_given_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Old name', 'price' => 10]);

        $response = $this->patchJson(route('api.v1.products.update', $product), ['price' => 12.5]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Old name')
            ->assertJsonPath('data.price', '12.50');
    }

    public function test_patch_still_validates_the_given_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->patchJson(route('api.v1.products.update', $product), ['price' => -5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }

    public function test_guest_cannot_update_a_product(): void
    {
        $product = Product::factory()->create();

        $this->putJson(route('api.v1.products.update', $product), $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_a_product_and_its_prices(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $productPrice = ProductPrice::factory()->create();
        $product = $productPrice->product;

        $this->deleteJson(route('api.v1.products.destroy', $product))
            ->assertNoContent();

        $this->assertModelMissing($product);
        $this->assertModelMissing($productPrice);
    }

    public function test_guest_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson(route('api.v1.products.destroy', $product))->assertUnauthorized();

        $this->assertModelExists($product);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Standing desk',
            'description' => 'Electric height adjustable desk.',
            'price' => 150,
            'currency_id' => Currency::factory()->create()->id,
            'tax_cost' => 28.5,
            'manufacturing_cost' => 70,
        ];
    }
}
