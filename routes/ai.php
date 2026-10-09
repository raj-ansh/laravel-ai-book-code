<?php

use App\Mcp\Servers\AcmeStaffServer;
use Laravel\Mcp\Facades\Mcp;

// Chapter 20: Acme's tools for MCP clients.
// Over HTTP, for remote clients, with a Sanctum token:
Mcp::web('/mcp/staff', AcmeStaffServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);

// Over stdio, for local clients: php artisan mcp:start acme-staff
Mcp::local('acme-staff', AcmeStaffServer::class);
