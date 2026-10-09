<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Two business customers with different plans and
     * settings, for Chapter 43.
     */
    public function run(): void
    {
        $tenants = [
            ['Brightlite Retail', 'pro', 'formal',
                'priya@brightlite.example', 'Priya Nair', 'A-3001'],
            ['Northwood Stores', 'basic', 'friendly',
                'karan@northwood.example', 'Karan Mehta', 'A-4001'],
            ['Corner Shop Co', 'none', null,
                'leela@cornershop.example', 'Leela Das', 'A-5001'],
        ];

        foreach ($tenants as [$name, $plan, $tone, $email,
            $person, $number]) {
            $organisation = Organisation::firstOrCreate(
                ['name' => $name],
                ['ai_plan' => $plan,
                    'ai_settings' => array_filter(['tone' => $tone])],
            );

            $user = User::firstOrCreate(['email' => $email], [
                'name' => $person, 'password' => 'password',
                'organisation_id' => $organisation->id,
            ]);

            Order::firstOrCreate(['number' => $number], [
                'user_id' => $user->id,
                'status' => OrderStatus::Shipped,
                'total_cents' => 18_450_000,
            ]);
        }
    }
}
