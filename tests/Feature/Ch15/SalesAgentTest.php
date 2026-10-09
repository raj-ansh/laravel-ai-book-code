<?php

use App\Ai\Agents\SalesAgent;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Tools\ToolSearch;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);

    Product::factory()->create([
        'sku' => 'SKU-1001',
        'name' => 'Brass desk lamp',
        'price_cents' => 459900,
        'stock' => 10,
    ]);
});

it('searches, prices and answers from tool results', function () {
    SalesAgent::fake([
        new ToolCall('call_1', 'search_products', [
            'query' => 'lamp',
        ]),
        new ToolCall('call_2', 'calculate_quote', ['items' => [
            ['sku' => 'SKU-1001', 'quantity' => 3],
        ]]),
        'Three brass desk lamps come to INR 13,107.15.',
    ]);

    $response = SalesAgent::make(customer: $this->asha)
        ->prompt('Price three desk lamps for me.');

    [$search, $quote] = $response->toolResults->all();

    expect($search->text())->toContain('SKU-1001')
        ->and(json_decode($quote->text(), true))
        ->subtotal->toBe('INR 13,797.00')
        ->discount->toBe('5% (INR 689.85)')
        ->total->toBe('INR 13,107.15');
});

it('takes prices from the database, not the model', function () {
    SalesAgent::fake([
        new ToolCall('call_1', 'create_quotation', ['items' => [
            ['sku' => 'SKU-1001', 'quantity' => 1,
                'unit_price' => 1], // invented by the model
        ]]),
        'Saved.',
    ]);

    SalesAgent::make(customer: $this->asha)
        ->prompt('Make me a quotation for one lamp.');

    expect(Quotation::sole()->total_cents)->toBe(459900);
});

it('never saves the same quotation twice', function () {
    $call = new ToolCall('call_7', 'create_quotation', ['items' => [
        ['sku' => 'SKU-1001', 'quantity' => 2],
    ]]);

    SalesAgent::fake([$call, $call, 'Saved.']);

    $response = SalesAgent::make(customer: $this->asha)
        ->prompt('Quote two lamps, please.');

    expect(Quotation::count())->toBe(1)
        ->and($response->toolResults->last()->text())
        ->toContain('already exists');
});

it('repairs a call to a tool that does not exist', function () {
    SalesAgent::fake([
        new ToolCall('call_1', 'get_price', ['sku' => 'SKU-1001']),
        'Let me use the right tool.',
    ]);

    $response = SalesAgent::make(customer: $this->asha)
        ->prompt('How much is SKU-1001?');

    expect($response->toolResults->first()->text())->toBe(
        "Tool 'get_price' does not exist. Available tools: "
            .'search_products, check_inventory, calculate_quote, '
            .'create_quotation.'
    );
});

it('defers rarely used tools only where supported', function () {
    Http::fake();

    SalesAgent::make(customer: $this->asha)
        ->withTools(fn (array $tools) => [
            $tools[0],
            new ToolSearch(tools: array_slice($tools, 1)),
        ])
        ->prompt('Hello', provider: Lab::Ollama);
})->throws(LogicException::class, 'does not support tool search');
