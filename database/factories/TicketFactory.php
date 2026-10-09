<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => 'Charged twice for order A-1001',
            'body' => 'I was charged twice for my last order. '
                .'Please refund one payment. - Asha',
        ];
    }
}
