<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnalyticsSeeder extends Seeder
{
    /**
     * Sixty days of orders, for the analytics chapter (40).
     * Seeded randomness, so every run creates the same orders.
     */
    public function run(): void
    {
        mt_srand(40);
        $customers = User::factory()->count(12)->create();
        $statuses = [OrderStatus::Delivered, OrderStatus::Delivered,
            OrderStatus::Shipped, OrderStatus::Processing,
            OrderStatus::Cancelled];

        foreach (range(1, 90) as $i) {
            Order::factory()->for($customers[mt_rand(0, 11)])->create([
                'number' => sprintf('B-%04d', $i),
                'status' => $statuses[mt_rand(0, 4)],
                'total_cents' => mt_rand(8, 260) * 10000 - 100,
                'created_at' => now()->subDays(mt_rand(0, 59))
                    ->setTime(mt_rand(8, 21), mt_rand(0, 59)),
            ]);
        }
    }
}
