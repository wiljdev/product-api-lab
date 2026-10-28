<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_currencies_ordered_by_code(): void
    {
        Currency::factory()->create(['name' => 'USD']);
        Currency::factory()->create(['name' => 'COP']);
        Currency::factory()->create(['name' => 'EUR']);

        $response = $this->getJson(route('api.v1.currencies.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.*.name', ['COP', 'EUR', 'USD'])
            ->assertJsonStructure(['data' => [['id', 'name', 'symbol', 'exchange_rate']]]);
    }

    public function test_exchange_rate_is_returned_with_six_decimals(): void
    {
        Currency::factory()->usd()->create();

        $this->getJson(route('api.v1.currencies.index'))
            ->assertOk()
            ->assertJsonPath('data.0.exchange_rate', '1.000000');
    }

    public function test_currency_list_does_not_expose_timestamps(): void
    {
        Currency::factory()->create();

        $this->getJson(route('api.v1.currencies.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
    }
}
