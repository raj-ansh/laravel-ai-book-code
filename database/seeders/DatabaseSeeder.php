<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Two Acme customers with a few orders each.
     */
    public function run(): void
    {
        $asha = User::factory()->create([
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
        ]);

        Order::factory()->for($asha)->shipped()->create([
            'number' => 'A-1001',
            'total_cents' => 459900,
            'tracking_number' => 'BD482913077',
        ]);

        Order::factory()->for($asha)->create([
            'number' => 'A-1002',
            'total_cents' => 129900,
        ]);

        $ravi = User::factory()->create([
            'name' => 'Ravi Menon',
            'email' => 'ravi@example.com',
        ]);

        Order::factory()->for($ravi)->shipped()->create([
            'number' => 'A-2001',
            'total_cents' => 89900,
        ]);

        $this->call([
            ProductSeeder::class,
            ReviewSeeder::class,
            CatalogueSeeder::class,
        ]);
    }
}
