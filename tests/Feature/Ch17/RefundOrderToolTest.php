<?php

use App\Ai\Tools\RefundOrder;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->asha = User::factory()->create();
    $this->tool = new RefundOrder($this->asha);

    Order::factory()->for($this->asha)->create([
        'number' => 'A-1001', 'total_cents' => 459900,
    ]);
    Order::factory()->for($this->asha)->create([
        'number' => 'A-1002', 'total_cents' => 79900,
    ]);
});

it('asks for approval above INR 1,000 only', function () {
    $large = $this->tool->shouldRequestApproval(
        new Request(['order_number' => 'A-1001'])
    );
    $small = $this->tool->shouldRequestApproval(
        new Request(['order_number' => 'A-1002'])
    );

    expect($large->reason)->toContain('needs a member of staff')
        ->and($small)->toBeNull();
});

it('refunds an order once, however often asked', function () {
    $request = new Request([
        'order_number' => 'A-1002', 'reason' => 'Arrived broken',
    ]);

    expect((string) $this->tool->handle($request))
        ->toStartWith('Refunded INR 799.00 for order A-1002')
        ->and((string) $this->tool->handle($request))
        ->toBe('Order A-1002 was already refunded.')
        ->and(Refund::count())->toBe(1);
});

it('never refunds someone else\'s order', function () {
    Order::factory()->create(['number' => 'A-2001']);

    expect((string) $this->tool->handle(new Request([
        'order_number' => 'A-2001', 'reason' => 'Please',
    ])))->toContain('No order A-2001 was found');
});
