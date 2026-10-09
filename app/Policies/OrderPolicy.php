<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Customers may see their own orders, and nobody else's.
     */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
