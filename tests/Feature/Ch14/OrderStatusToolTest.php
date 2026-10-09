<?php

use App\Ai\Tools\OrderStatusTool;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->asha = User::factory()->create();
    $this->tool = new OrderStatusTool($this->asha);

    Order::factory()->for($this->asha)->shipped()->create([
        'number' => 'A-1001',
        'tracking_number' => 'BD482913077',
    ]);

    // Ravi's order: it exists, but not for Asha.
    Order::factory()->create(['number' => 'A-2001']);
});

it('returns the status of the customer\'s order', function () {
    $result = json_decode($this->tool->handle(
        new Request(['order_number' => 'A-1001'])
    ), true);

    expect($result)
        ->status->toBe('shipped')
        ->carrier->toBe('BlueDart')
        ->tracking_number->toBe('BD482913077');
});

it('hides orders that belong to someone else', function (
    string $number,
) {
    expect((string) $this->tool->handle(
        new Request(['order_number' => $number])
    ))->toBe("No order {$number} was found for this customer.");
})->with(['A-2001', 'A-9999']);

it('rejects a malformed order number', function () {
    $this->tool->handle(new Request(['order_number' => '1001']));
})->throws(ValidationException::class, 'format is invalid');

it('is called get_order_status', function () {
    expect($this->tool->name())->toBe('get_order_status');
});
