<?php

namespace App\Services\Sales;

use App\Models\Product;
use App\Models\User;
use InvalidArgumentException;

/**
 * All pricing happens here, in PHP. The model asks; this class
 * calculates. A model is never trusted with arithmetic on money.
 */
class QuoteCalculator
{
    /**
     * @param  list<array{sku: string, quantity: int}>  $items
     *
     * @throws InvalidArgumentException for unknown SKUs or stock
     */
    public function calculate(array $items, User $customer): Quote
    {
        $products = Product::query()
            ->whereIn('sku', array_column($items, 'sku'))
            ->get()
            ->keyBy('sku');

        $lines = [];

        foreach ($items as $item) {
            $product = $products[$item['sku']]
                ?? throw new InvalidArgumentException(
                    "There is no product with SKU {$item['sku']}."
                );

            if ($product->stock < $item['quantity']) {
                throw new InvalidArgumentException(
                    "Only {$product->stock} of {$product->sku} "
                    .'in stock.'
                );
            }

            $lines[] = [
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $item['quantity'],
                'unit_cents' => $product->price_cents,
                'line_cents' => $product->price_cents
                    * $item['quantity'],
            ];
        }

        $subtotal = array_sum(array_column($lines, 'line_cents'));
        $percent = $this->discountPercent($subtotal, $customer);
        $discount = intdiv($subtotal * $percent, 100);

        return new Quote(
            $lines, $subtotal, $percent, $discount,
            $subtotal - $discount,
        );
    }

    private function discountPercent(
        int $subtotal,
        User $customer,
    ): int {
        $percent = match (true) {
            $subtotal >= 5_000_000 => 10, // INR 50,000 or more
            $subtotal >= 1_000_000 => 5,  // INR 10,000 or more
            default => 0,
        };

        if ($customer->orders()->count() >= 3) {
            $percent += 2; // loyal customers
        }

        return min($percent, 12);
    }
}
