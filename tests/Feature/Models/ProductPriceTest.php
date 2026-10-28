<?php

namespace Tests\Feature\Models;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_price_can_be_persisted(): void
    {
        $product = Product::factory()->create();
        $currency = Currency::factory()->create(['name' => 'EUR']);

        $productPrice = ProductPrice::factory()->for($product)->for($currency)->create([
            'price' => 99.9,
        ]);

        $this->assertDatabaseHas('product_prices', [
            'id' => $productPrice->id,
            'product_id' => $product->id,
            'currency_id' => $currency->id,
        ]);
        $this->assertSame('99.90', $productPrice->fresh()->price);
    }

    public function test_product_price_belongs_to_product_and_currency(): void
    {
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();

        $productPrice = ProductPrice::factory()->for($product)->for($currency)->create();

        $this->assertTrue($productPrice->product->is($product));
        $this->assertTrue($productPrice->currency->is($currency));
    }

    public function test_product_and_currency_have_many_prices(): void
    {
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();
        ProductPrice::factory()->for($product)->for($currency)->create();
        ProductPrice::factory()->for($product)->create();

        $this->assertCount(2, $product->prices);
        $this->assertCount(1, $currency->productPrices);
    }

    public function test_product_cannot_have_two_prices_in_the_same_currency(): void
    {
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();
        ProductPrice::factory()->for($product)->for($currency)->create();

        $this->expectException(QueryException::class);

        ProductPrice::factory()->for($product)->for($currency)->create();
    }

    public function test_deleting_a_product_deletes_its_prices(): void
    {
        $productPrice = ProductPrice::factory()->create();

        $productPrice->product->delete();

        $this->assertModelMissing($productPrice);
    }

    public function test_currency_used_by_a_price_cannot_be_deleted(): void
    {
        $productPrice = ProductPrice::factory()->create();

        $this->expectException(QueryException::class);

        $productPrice->currency->delete();
    }
}
