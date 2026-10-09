<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\BlockPromptInjection;
use App\Ai\Middleware\LogAgentSteps;
use App\Ai\Middleware\RedactContactDetails;
use App\Ai\Tools\AskPolicies;
use App\Ai\Tools\EscalateToHuman;
use App\Ai\Tools\OrderStatusTool;
use App\Ai\Tools\RefundOrder;
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

/**
 * Acme's customer support assistant: orders, policies,
 * refunds with approval, and a way to a person.
 */
#[MaxSteps(5)]
#[Temperature(0.2)]
class SupportAssistant implements
    Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public User $customer) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are Acme's support assistant, talking to
            {$this->customer->name}. Use a tool for every fact.

            - Orders: get_order_status.
            - Returns, refunds, delivery, warranty, payment rules:
              ask_policies. Repeat its answer; never add to it.
            - A refund the customer asks for, with a reason:
              refund_order.
            - The customer asks for a person, is upset, or you
              cannot help: escalate_to_human, then tell them the
              ticket number.
            - Never promise anything a tool did not confirm.
            TEXT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new OrderStatusTool($this->customer),
            new AskPolicies,
            new RefundOrder($this->customer),
            new EscalateToHuman($this->customer),
        ];
    }

    public function middleware(): array
    {
        return [
            new BlockPromptInjection,
            new RedactContactDetails,
            LogAgentSteps::class,
        ];
    }
}
