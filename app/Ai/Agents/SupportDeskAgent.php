<?php

namespace App\Ai\Agents;

use App\Ai\Tools\OrderStatusTool;
use App\Ai\Tools\RefundOrder;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(4)]
#[Temperature(0.2)]
class SupportDeskAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public User $customer) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are Acme's support assistant, talking to
            {$this->customer->name}.

            - Look up orders with get_order_status.
            - If the customer asks for a refund and gives a
              reason, call refund_order. Large refunds wait for
              a member of staff; tell the customer that.
            - Never say a refund is done unless refund_order
              returned "Refunded".
            TEXT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new OrderStatusTool($this->customer),
            new RefundOrder($this->customer),
        ];
    }
}
