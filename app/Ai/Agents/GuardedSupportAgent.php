<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\BlockPromptInjection;
use App\Ai\Middleware\LogAgentSteps;
use App\Ai\Middleware\RedactContactDetails;
use App\Ai\Middleware\TrimHistory;
use App\Ai\Tools\OrderStatusTool;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(4)]
#[Temperature(0.2)]
class GuardedSupportAgent implements
    Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public User $customer) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are Acme's support assistant, talking to
            {$this->customer->name}. Look up orders with
            get_order_status. Keep answers short. For anything
            else, the customer can write to support@acme.test.
            TEXT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new OrderStatusTool($this->customer),
        ];
    }

    /**
     * Wrap every generation step, outermost first.
     */
    public function middleware(): array
    {
        return [
            new BlockPromptInjection,
            new RedactContactDetails,
            new TrimHistory(keep: 12),
            LogAgentSteps::class,
        ];
    }
}
