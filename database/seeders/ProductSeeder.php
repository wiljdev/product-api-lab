<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Demo products with their prices in their base currency.
     *
     * @var list<array{name: string, description: string, currency: string, price: float, tax_cost: float, manufacturing_cost: float}>
     */
    public const PRODUCTS = [
        [
            'name' => 'Teclado mecánico TKL',
            'description' => 'Teclado mecánico sin pad numérico con switches táctiles y retroiluminación RGB.',
            'currency' => 'USD',
            'price' => 89.90,
            'tax_cost' => 17.08,
            'manufacturing_cost' => 32.50,
        ],
        [
            'name' => 'Mouse inalámbrico ergonómico',
            'description' => 'Mouse vertical inalámbrico con sensor de 4000 DPI y batería recargable.',
            'currency' => 'USD',
            'price' => 45.00,
            'tax_cost' => 8.55,
            'manufacturing_cost' => 14.20,
        ],
        [
            'name' => 'Monitor 27" QHD',
            'description' => 'Monitor IPS de 27 pulgadas, resolución 2560x1440 y 165 Hz.',
            'currency' => 'USD',
            'price' => 329.00,
            'tax_cost' => 62.51,
            'manufacturing_cost' => 180.00,
        ],
        [
            'name' => 'Base refrigerante para portátil',
            'description' => 'Base de aluminio con dos ventiladores silenciosos y altura regulable.',
            'currency' => 'EUR',
            'price' => 34.99,
            'tax_cost' => 6.65,
            'manufacturing_cost' => 11.00,
        ],
        [
            'name' => 'Auriculares con cancelación de ruido',
            'description' => 'Auriculares Bluetooth over-ear con cancelación activa de ruido y 30 horas de autonomía.',
            'currency' => 'EUR',
            'price' => 199.00,
            'tax_cost' => 37.81,
            'manufacturing_cost' => 85.00,
        ],
        [
            'name' => 'Silla ergonómica de oficina',
            'description' => 'Silla con soporte lumbar ajustable, reposabrazos 3D y malla transpirable.',
            'currency' => 'COP',
            'price' => 1250000.00,
            'tax_cost' => 237500.00,
            'manufacturing_cost' => 610000.00,
        ],
        [
            'name' => 'Hub USB-C 7 en 1',
            'description' => 'Adaptador USB-C con HDMI 4K, lector SD, tres puertos USB-A y carga de 100 W.',
            'currency' => 'MXN',
            'price' => 899.00,
            'tax_cost' => 143.84,
            'manufacturing_cost' => 310.00,
        ],
        [
            'name' => 'Webcam Full HD',
            'description' => 'Cámara web 1080p a 60 fps con micrófono estéreo y enfoque automático.',
            'currency' => 'GBP',
            'price' => 59.00,
            'tax_cost' => 11.80,
            'manufacturing_cost' => 21.00,
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Every product gets a price in each currency other than its base one,
     * derived from the demo exchange rates.
     */
    public function run(): void
    {
        $currencies = Currency::all()->keyBy('name');

        foreach (self::PRODUCTS as $data) {
            $baseCurrency = $currencies->get($data['currency']);

            $product = Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'currency_id' => $baseCurrency->id,
                    'price' => $data['price'],
                    'tax_cost' => $data['tax_cost'],
                    'manufacturing_cost' => $data['manufacturing_cost'],
                ],
            );

            foreach ($currencies->reject(fn (Currency $currency) => $currency->is($baseCurrency)) as $currency) {
                $product->prices()->updateOrCreate(
                    ['currency_id' => $currency->id],
                    ['price' => round($data['price'] / $baseCurrency->exchange_rate * $currency->exchange_rate, 2)],
                );
            }
        }
    }
}
