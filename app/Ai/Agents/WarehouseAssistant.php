<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Mcp\Facades\Mcp;
use Stringable;

#[MaxSteps(3)]
class WarehouseAssistant implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You help the warehouse team. Use the tools for '
            .'every fact about products and orders.';
    }

    /**
     * Whatever tools the remote MCP server offers this client.
     */
    public function tools(): iterable
    {
        return Mcp::client('acme-staff')->tools();
    }
}
