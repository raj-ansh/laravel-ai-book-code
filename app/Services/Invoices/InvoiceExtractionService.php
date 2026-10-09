<?php

namespace App\Services\Invoices;

use App\Ai\Agents\InvoiceExtractor;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/**
 * Extract with a model, then trust nothing: validate the
 * shape, check it against the source, check the arithmetic.
 */
class InvoiceExtractionService
{
    public function extract(string $source): ExtractionResult
    {
        $data = InvoiceExtractor::make()->prompt($source)->toArray();

        // 1. Shape: the same rules as a form submission.
        $validator = Validator::make($data, [
            'supplier' => ['required', 'string', 'max:200'],
            'invoice_number' => ['required', 'string', 'max:50'],
            'invoice_date' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['required', 'in:INR,USD,EUR'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return new ExtractionResult(
                null, $validator->errors()->all(),
            );
        }

        $invoice = $this->toInvoice($validator->validated());

        return new ExtractionResult(
            $invoice, $this->issues($invoice, $source),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toInvoice(array $data): InvoiceData
    {
        return new InvoiceData(
            supplier: $data['supplier'],
            number: $data['invoice_number'],
            date: $data['invoice_date'] === null ? null
                : CarbonImmutable::parse($data['invoice_date']),
            currency: $data['currency'],
            lines: array_map(fn (array $line) => [
                'description' => $line['description'],
                'quantity' => (int) $line['quantity'],
                'unit_cents' => (int) round($line['unit_price'] * 100),
            ], $data['lines']),
            totalCents: (int) round($data['total'] * 100),
        );
    }

    /**
     * 2. Grounding and 3. arithmetic, in code.
     *
     * @return list<string>
     */
    private function issues(InvoiceData $invoice, string $source): array
    {
        $issues = [];

        if (! str_contains($source, $invoice->number)) {
            $issues[] = "Invoice number {$invoice->number} "
                .'does not appear in the source.';
        }

        if ($invoice->date === null) {
            $issues[] = 'The invoice date is missing.';
        } elseif ($invoice->date->isFuture()) {
            $issues[] = 'The invoice date is in the future.';
        }

        if ($invoice->linesTotalCents() !== $invoice->totalCents) {
            $issues[] = 'Line items add up to '
                .Money::inr($invoice->linesTotalCents())
                .', the invoice says '
                .Money::inr($invoice->totalCents).'.';
        }

        return $issues;
    }
}
