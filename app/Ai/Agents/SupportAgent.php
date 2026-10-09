<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FindCustomerOrders;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(4)]
#[Temperature(0.2)]
#[MaxTokens(400)]
class SupportAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(public User $customer) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are the support assistant of Acme, an online store.
            You are talking to {$this->customer->name}.

            - Answer questions about this customer's orders.
            - Use the tools to look up facts. Never guess an
              order number, a status or a delivery date.
            - If the tools do not have the answer, say so and
              offer to pass the question to a human agent.
            - Keep answers short and friendly.
            TEXT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new FindCustomerOrders($this->customer),
        ];
    }
}
