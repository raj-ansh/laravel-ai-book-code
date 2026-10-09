<?php

use App\Ai\Agents\StockAssistant;
use App\Ai\Agents\WarehouseAssistant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    Product::factory()->create([
        'sku' => 'SKU-1001', 'name' => 'Brass desk lamp',
        'price_cents' => 459900, 'stock' => 25,
    ]);
});

it('uses an MCP server tool as an agent tool', function () {
    StockAssistant::fake([
        new ToolCall('call_1', 'check-stock', ['query' => 'lamp']),
        'We have 25 brass desk lamps.',
    ]);

    $response = StockAssistant::make()->prompt('Lamps in stock?');

    expect($response->toolResults->first()->text())
        ->toContain('SKU-1001 Brass desk lamp')
        ->toContain('25 in stock');
});

it('calls a remote MCP server through the client', function () {
    Order::factory()->create(['number' => 'A-1001']);
    $staff = User::factory()->staff()->create();

    config(['services.acme_mcp' => [
        'url' => 'http://localhost/mcp/staff',
        'token' => $staff->createToken('warehouse')->plainTextToken,
    ]]);

    // Send the client's HTTP requests to this app's MCP route.
    Http::fake(function (Request $request) {
        $response = $this->call(
            $request->method(), '/mcp/staff', server: $this
                ->transformHeadersToServerVars(array_map(
                    fn (array $values) => $values[0],
                    $request->headers(),
                )),
            content: $request->body(),
        );

        return Http::response(
            $response->getContent(),
            $response->getStatusCode(),
            $response->headers->all(),
        );
    });

    WarehouseAssistant::fake([
        new ToolCall('call_1', 'mcp_tools_lookup-order', [
            'number' => 'A-1001',
        ]),
        'Order A-1001 has shipped.',
    ]);

    $response = WarehouseAssistant::make()
        ->prompt('What is the status of A-1001?');

    expect($response->toolResults->first()->text())
        ->toStartWith('Order A-1001 for');
});
