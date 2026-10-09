<?php

namespace App\Services\Invoices;

use Carbon\CarbonImmutable;

final readonly class InvoiceData
{
    /**
     * @param  list<array{description: string, quantity: int,
     *     unit_cents: int}>  $lines
     */
    public function __construct(
        public string $supplier,
        public string $number,
        public ?CarbonImmutable $date,
        public string $currency,
        public array $lines,
        public int $totalCents,
    ) {}

    public function linesTotalCents(): int
    {
        return array_sum(array_map(
            fn (array $line) => $line['quantity'] * $line['unit_cents'],
            $this->lines,
        ));
    }
}
