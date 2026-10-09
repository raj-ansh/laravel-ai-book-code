<?php

namespace App\Ai\Agents;

use App\Ai\Tools\Sales\CalculateQuote;
use App\Ai\Tools\Sales\CheckInventory;
use App\Ai\Tools\Sales\CreateQuotation;
use App\Ai\Tools\Sales\SearchProducts;
use App\Models\User;
use App\Services\Sales\QuoteCalculator;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(6)]
#[RepairToolCalls]
#[Temperature(0.2)]
class SalesAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public User $customer,
        private QuoteCalculator $calculator,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<TEXT
            You are Acme's sales assistant, helping
            {$this->customer->name} choose and price products.

            - Find products with search_products; quote SKUs
              exactly as the tools return them.
            - Every price, discount and total must come from
              calculate_quote. Never do arithmetic yourself.
            - Save a quotation only when the customer asks for one.
            TEXT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new SearchProducts,
            new CheckInventory,
            new CalculateQuote($this->customer, $this->calculator),
            new CreateQuotation($this->customer, $this->calculator),
        ];
    }
}
