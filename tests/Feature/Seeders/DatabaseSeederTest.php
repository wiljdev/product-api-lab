<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_configured_demo_users_with_their_roles(): void
    {
        config([
            'demo.user.email' => 'editor@example.com',
            'demo.user.password' => 'secret-editor-password',
            'demo.viewer.email' => 'viewer@example.com',
            'demo.viewer.password' => 'secret-viewer-password',
        ]);

        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $this->assertDatabaseCount(User::class, 2);

        $editor = User::firstWhere('email', 'editor@example.com');
        $this->assertSame(UserRole::Editor, $editor->role);
        $this->assertTrue(Hash::check('secret-editor-password', $editor->password));

        $viewer = User::firstWhere('email', 'viewer@example.com');
        $this->assertSame(UserRole::Viewer, $viewer->role);
        $this->assertTrue(Hash::check('secret-viewer-password', $viewer->password));
    }

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
