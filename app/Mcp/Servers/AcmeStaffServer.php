<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ReturnsPolicy;
use App\Mcp\Tools\CheckStock;
use App\Mcp\Tools\LookupOrder;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Acme Staff')]
#[Version('1.0.0')]
#[Instructions('Read-only tools for Acme staff: product stock, '
    .'orders and the returns policy.')]
class AcmeStaffServer extends Server
{
    protected array $tools = [
        CheckStock::class,
        LookupOrder::class,
    ];

    protected array $resources = [
        ReturnsPolicy::class,
    ];

    protected array $prompts = [
        //
    ];
}
