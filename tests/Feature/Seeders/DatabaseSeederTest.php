<?php

namespace Tests\Feature\Seeders;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_demo_currencies_products_and_prices(): void
    {
        $this->seed(DatabaseSeeder::class);

        $currencyCount = count(CurrencySeeder::CURRENCIES);
        $productCount = count(ProductSeeder::PRODUCTS);

        $this->assertDatabaseCount(Currency::class, $currencyCount);
        $this->assertDatabaseCount(Product::class, $productCount);
        $this->assertDatabaseCount(ProductPrice::class, $productCount * ($currencyCount - 1));
    }

    public function test_usd_is_the_exchange_rate_reference(): void
    {
        $this->seed(CurrencySeeder::class);

        $this->assertSame('1.000000', Currency::firstWhere('name', 'USD')->exchange_rate);
    }

    public function test_products_have_no_price_in_their_base_currency(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(
            Product::whereHas('prices', fn ($query) => $query->whereColumn('product_prices.currency_id', 'products.currency_id'))->exists()
        );
    }

    public function test_prices_are_derived_from_exchange_rates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::firstWhere('name', 'Teclado mecánico TKL');
        $eur = Currency::firstWhere('name', 'EUR');

        $this->assertSame('82.71', $product->prices()->whereBelongsTo($eur)->value('price'));
    }

    public function test_seeding_twice_does_not_duplicate_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount(Currency::class, count(CurrencySeeder::CURRENCIES));
        $this->assertDatabaseCount(Product::class, count(ProductSeeder::PRODUCTS));
    }
}
