<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Demo currencies with approximate exchange rates against 1 USD.
     *
     * @var list<array{name: string, symbol: string, exchange_rate: float}>
     */
    public const CURRENCIES = [
        ['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1],
        ['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92],
        ['name' => 'GBP', 'symbol' => '£', 'exchange_rate' => 0.79],
        ['name' => 'MXN', 'symbol' => '$', 'exchange_rate' => 18.5],
        ['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4150],
        ['name' => 'PEN', 'symbol' => 'S/', 'exchange_rate' => 3.75],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CURRENCIES as $currency) {
            Currency::updateOrCreate(
                ['name' => $currency['name']],
                ['symbol' => $currency['symbol'], 'exchange_rate' => $currency['exchange_rate']],
            );
        }
    }
}
