<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => 'A-'.fake()->unique()
                ->numberBetween(1000, 9999),
            'status' => OrderStatus::Processing,
            'total_cents' => fake()->numberBetween(1500, 250000),
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Shipped,
            'carrier' => 'BlueDart',
            'tracking_number' => 'BD'.fake()->numerify('#########'),
            'shipped_at' => now()->subDays(2),
        ]);
    }
}
