<?php

use App\Mcp\Resources\ReturnsPolicy;
use App\Mcp\Servers\AcmeStaffServer;
use App\Mcp\Tools\CheckStock;
use App\Mcp\Tools\LookupOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    Product::factory()->create([
        'sku' => 'SKU-1001', 'name' => 'Brass desk lamp',
        'price_cents' => 459900, 'stock' => 25,
    ]);
    Order::factory()
        ->for(User::factory()->create(['name' => 'Asha']))
        ->shipped()
        ->create(['number' => 'A-1001', 'total_cents' => 459900]);
});

it('finds products by name or SKU', function () {
    AcmeStaffServer::tool(CheckStock::class, ['query' => 'lamp'])
        ->assertOk()
        ->assertSee([
            'SKU-1001 Brass desk lamp',
            'INR 4,599.00, 25 in stock',
        ]);

    AcmeStaffServer::tool(CheckStock::class, ['query' => 'sofa'])
        ->assertSee('No product matches "sofa".');
});

it('validates tool arguments', function () {
    AcmeStaffServer::tool(CheckStock::class, ['query' => 'x'])
        ->assertHasErrors();
});

it('shows staff the order lookup tool', function () {
    $staff = User::factory()->staff()->create();

    AcmeStaffServer::actingAs($staff)
        ->tool(LookupOrder::class, ['number' => 'A-1001'])
        ->assertOk()
        ->assertSee('Order A-1001 for Asha: shipped, INR 4,599.00.');
});

it('hides the order lookup tool from everyone else', function () {
    AcmeStaffServer::actingAs(User::factory()->create())
        ->tool(LookupOrder::class, ['number' => 'A-1001'])
        ->assertNotRegistered();
});

it('serves the returns policy as a resource', function () {
    AcmeStaffServer::resource(ReturnsPolicy::class)
        ->assertOk()
        ->assertSee('within 30 days of delivery');
});
