<?php

namespace Tests\Feature\Models;

use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_can_be_persisted(): void
    {
        $currency = Currency::factory()->create([
            'name' => 'EUR',
            'symbol' => '€',
            'exchange_rate' => 0.92,
        ]);

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'EUR',
            'symbol' => '€',
        ]);
    }

    public function test_exchange_rate_is_cast_to_six_decimals(): void
    {
        $currency = Currency::factory()->create(['exchange_rate' => 4150.5]);

        $this->assertSame('4150.500000', $currency->fresh()->exchange_rate);
    }

    public function test_usd_state_is_the_exchange_rate_reference(): void
    {
        $currency = Currency::factory()->usd()->create();

        $this->assertSame('USD', $currency->name);
        $this->assertSame('1.000000', $currency->fresh()->exchange_rate);
    }

    public function test_currency_name_must_be_unique(): void
    {
        Currency::factory()->create(['name' => 'COP']);

        $this->expectException(QueryException::class);

        Currency::factory()->create(['name' => 'COP']);
    }
}
