<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(50000, 900000),
            'stock' => fake()->numberBetween(0, 40),
        ];
    }
}
