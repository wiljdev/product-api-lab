<?php

namespace Tests\Feature\Models;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_persisted(): void
    {
        $currency = Currency::factory()->usd()->create();

        $product = Product::factory()->for($currency)->create([
            'name' => 'Mechanical keyboard',
            'price' => 120.5,
            'tax_cost' => 22.9,
            'manufacturing_cost' => 45,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Mechanical keyboard',
            'currency_id' => $currency->id,
        ]);
    }

    public function test_monetary_attributes_are_cast_to_two_decimals(): void
    {
        $product = Product::factory()->create([
            'price' => 120.5,
            'tax_cost' => 22.9,
            'manufacturing_cost' => 45,
        ])->fresh();

        $this->assertSame('120.50', $product->price);
        $this->assertSame('22.90', $product->tax_cost);
        $this->assertSame('45.00', $product->manufacturing_cost);
    }

    public function test_product_belongs_to_its_base_currency(): void
    {
        $currency = Currency::factory()->create();
        $product = Product::factory()->for($currency)->create();

        $this->assertTrue($product->currency->is($currency));
    }

    public function test_currency_has_many_products(): void
    {
        $currency = Currency::factory()->create();
        Product::factory()->count(2)->for($currency)->create();
        Product::factory()->create();

        $this->assertCount(2, $currency->products);
    }

    public function test_currency_in_use_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        $product->currency->delete();
    }
}
