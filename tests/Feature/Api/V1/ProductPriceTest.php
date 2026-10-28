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

class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_the_prices_of_a_product(): void
    {
        $product = Product::factory()->create();
        ProductPrice::factory()->count(2)->for($product)->create();
        ProductPrice::factory()->create();

        $response = $this->getJson(route('api.v1.products.prices.index', $product));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'product_id', 'currency_id',
                    'currency' => ['id', 'name', 'symbol', 'exchange_rate'],
                    'price', 'created_at', 'updated_at',
                ]],
            ]);
    }

    public function test_product_without_prices_returns_an_empty_list(): void
    {
        $product = Product::factory()->create();

        $this->getJson(route('api.v1.products.prices.index', $product))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_listing_prices_of_a_missing_product_returns_not_found(): void
    {
        $this->getJson(route('api.v1.products.prices.index', 999))->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_price(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();

        $response = $this->postJson(route('api.v1.products.prices.store', $product), [
            'currency_id' => $currency->id,
            'price' => 75.5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.currency.id', $currency->id)
            ->assertJsonPath('data.price', '75.50');
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'currency_id' => $currency->id,
        ]);
    }

    public function test_guest_cannot_create_a_price(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('api.v1.products.prices.store', $product), [
            'currency_id' => Currency::factory()->create()->id,
            'price' => 10,
        ])->assertUnauthorized();

        $this->assertDatabaseEmpty('product_prices');
    }

    public function test_creating_a_price_for_a_missing_product_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.products.prices.store', 999), [
            'currency_id' => Currency::factory()->create()->id,
            'price' => 10,
        ])->assertNotFound();
    }

    public function test_price_cannot_use_the_product_base_currency(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->postJson(route('api.v1.products.prices.store', $product), [
            'currency_id' => $product->currency_id,
            'price' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('currency_id');
    }

    public function test_product_cannot_have_two_prices_in_the_same_currency(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $productPrice = ProductPrice::factory()->create();

        $this->postJson(route('api.v1.products.prices.store', $productPrice->product_id), [
            'currency_id' => $productPrice->currency_id,
            'price' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'currency_id' => 'already has a price in this currency',
        ]);
    }

    public function test_same_currency_can_be_used_by_different_products(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $productPrice = ProductPrice::factory()->create();
        $otherProduct = Product::factory()->create();

        $this->postJson(route('api.v1.products.prices.store', $otherProduct), [
            'currency_id' => $productPrice->currency_id,
            'price' => 10,
        ])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_price_creation_is_validated(array $payload, string $invalidField): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();

        $response = $this->postJson(
            route('api.v1.products.prices.store', $product),
            [...['currency_id' => $currency->id, 'price' => 10], ...$payload],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors($invalidField);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing currency' => [['currency_id' => null], 'currency_id'],
            'unknown currency' => [['currency_id' => 999], 'currency_id'],
            'missing price' => [['price' => null], 'price'],
            'negative price' => [['price' => -1], 'price'],
            'price with three decimals' => [['price' => 1.999], 'price'],
        ];
    }
}
