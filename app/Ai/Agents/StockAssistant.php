<?php

namespace App\Ai\Agents;

use App\Mcp\Tools\CheckStock;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(3)]
class StockAssistant implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You help Acme staff with stock questions. Use '
            .'the check-stock tool for every number you give.';
    }

    /**
     * An MCP server tool, used directly as an agent tool.
     */
    public function tools(): iterable
    {
        return [
            new CheckStock,
        ];
    }
}
