<?php

namespace App\Ai\Agents;

use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(5)]
#[Temperature(0.2)]
class SupportConcierge implements Agent, HasTools
{
    use Promptable;

    public function __construct(public User $customer) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are Acme's front desk, talking to
            {$this->customer->name}. You know no facts yourself.

            - For orders, ask the orders specialist.
            - For returns, shipping and warranty rules, ask the
              policy specialist.
            - A question may need both. Combine their answers
              into one short, friendly reply.
            TEXT;
    }

    /**
     * Agents listed here become tools of this agent.
     *
     * @return list<Agent>
     */
    public function tools(): iterable
    {
        return [
            new OrdersSpecialist($this->customer),
            new PolicySpecialist,
        ];
    }
}
