<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Sales\QuoteCalculator;

beforeEach(function () {
    $this->calculator = app(QuoteCalculator::class);
    $this->customer = User::factory()->create();

    Product::factory()->create([
        'sku' => 'SKU-1001', 'price_cents' => 459900, 'stock' => 20,
    ]);
});

it('prices lines and applies volume discounts', function (
    int $quantity, int $percent, int $total,
) {
    $quote = $this->calculator->calculate(
        [['sku' => 'SKU-1001', 'quantity' => $quantity]],
        $this->customer,
    );

    expect($quote->discountPercent)->toBe($percent)
        ->and($quote->totalCents)->toBe($total);
})->with([
    'under INR 10,000' => [1, 0, 459900],
    'INR 10,000 or more' => [3, 5, 1310715],
    'INR 50,000 or more' => [11, 10, 4553010],
]);

it('adds a loyalty discount, capped at 12%', function () {
    Order::factory()->count(3)->for($this->customer)->create();

    $quote = $this->calculator->calculate(
        [['sku' => 'SKU-1001', 'quantity' => 11]],
        $this->customer,
    );

    expect($quote->discountPercent)->toBe(12);
});

it('refuses unknown products and short stock', function (
    array $item, string $message,
) {
    expect(fn () => $this->calculator->calculate(
        [$item], $this->customer,
    ))->toThrow(InvalidArgumentException::class, $message);
})->with([
    [['sku' => 'SKU-9999', 'quantity' => 1], 'no product'],
    [['sku' => 'SKU-1001', 'quantity' => 21], 'Only 20'],
]);
