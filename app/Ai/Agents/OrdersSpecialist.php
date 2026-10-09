<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FindCustomerOrders;
use App\Ai\Tools\OrderStatusTool;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(3)]
#[Temperature(0.0)]
class OrdersSpecialist implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(private User $customer) {}

    public function name(): string
    {
        return 'ask_orders_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Ask about this customer\'s orders: status, '
            .'delivery, items. Write a complete question and '
            .'include any order number the customer gave.';
    }

    public function instructions(): Stringable|string
    {
        return 'You answer questions about one customer\'s '
            .'orders for another assistant. Use the tools for '
            .'every fact. Reply in at most three sentences.';
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new OrderStatusTool($this->customer),
            new FindCustomerOrders($this->customer),
        ];
    }
}
