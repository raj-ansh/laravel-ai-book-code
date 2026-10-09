<?php

namespace App\Services\Invoices;

final readonly class ExtractionResult
{
    /**
     * @param  list<string>  $issues  Why a person must check it.
     */
    public function __construct(
        public ?InvoiceData $invoice,
        public array $issues,
    ) {}

    public function needsReview(): bool
    {
        return $this->invoice === null || $this->issues !== [];
    }
}
