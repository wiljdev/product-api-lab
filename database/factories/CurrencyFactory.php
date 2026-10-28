<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->currencyCode(),
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', '₡', 'S/']),
            'exchange_rate' => fake()->randomFloat(6, 0.1, 5000),
        ];
    }

    /**
     * Indicate that the currency is the US dollar, the exchange rate reference.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 1,
        ]);
    }
}
