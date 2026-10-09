<?php

namespace App\Services\Sales;

use App\Support\Money;

final readonly class Quote
{
    /**
     * @param  list<array{sku: string, name: string, quantity: int,
     *   unit_cents: int, line_cents: int}>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalCents,
        public int $discountPercent,
        public int $discountCents,
        public int $totalCents,
    ) {}

    /**
     * What a tool returns to the model: every amount formatted.
     *
     * @return array<string, mixed>
     */
    public function forModel(): array
    {
        return [
            'lines' => array_map(fn (array $line) => [
                'sku' => $line['sku'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_price' => Money::inr($line['unit_cents']),
                'line_total' => Money::inr($line['line_cents']),
            ], $this->lines),
            'subtotal' => Money::inr($this->subtotalCents),
            'discount' => "{$this->discountPercent}% ("
                .Money::inr($this->discountCents).')',
            'total' => Money::inr($this->totalCents),
        ];
    }
}
