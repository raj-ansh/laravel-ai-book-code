<?php

use App\Ai\Agents\InvoiceExtractor;
use App\Services\Invoices\InvoiceExtractionService;

beforeEach(function () {
    $this->source = file_get_contents(
        resource_path('samples/invoices/brightlite.txt')
    );
    $this->service = new InvoiceExtractionService;
});

/** The BrightLite invoice, as a model should extract it. */
function brightlite(array $overrides = []): array
{
    return array_replace([
        'supplier' => 'BrightLite Lighting Pvt. Ltd.',
        'invoice_number' => 'BL-2026-0912',
        'invoice_date' => '2026-09-28',
        'currency' => 'INR',
        'lines' => [
            ['description' => 'Brass desk lamp (wholesale)',
                'quantity' => 20, 'unit_price' => 2150.00],
            ['description' => 'Warm LED bulb, 8W',
                'quantity' => 40, 'unit_price' => 95.00],
            ['description' => 'Packaging and freight',
                'quantity' => 1, 'unit_price' => 1200.00],
        ],
        'total' => 48000.00,
    ], $overrides);
}

it('extracts a consistent invoice', function () {
    InvoiceExtractor::fake([brightlite()]);

    $result = $this->service->extract($this->source);

    expect($result->needsReview())->toBeFalse()
        ->and($result->invoice->totalCents)->toBe(4_800_000)
        ->and($result->invoice->lines[0]['unit_cents'])
        ->toBe(215_000)
        ->and($result->invoice->date->toDateString())
        ->toBe('2026-09-28');
});

it('rejects output with the wrong shape', function () {
    InvoiceExtractor::fake([brightlite([
        'invoice_date' => '28/09/2026', 'lines' => [],
    ])]);

    $result = $this->service->extract($this->source);

    expect($result->invoice)->toBeNull()
        ->and($result->issues)->toContain(
            'The invoice date field must match the format Y-m-d.',
            'The lines field is required.',
        );
});

it('flags totals that do not add up', function () {
    InvoiceExtractor::fake([brightlite(['total' => 46800.00])]);

    expect($this->service->extract($this->source)->issues)
        ->toBe(['Line items add up to INR 48,000.00, '
            .'the invoice says INR 46,800.00.']);
});

it('flags an invoice number that is not in the source', function () {
    InvoiceExtractor::fake([brightlite([
        'invoice_number' => 'BL-2026-0921',
    ])]);

    expect($this->service->extract($this->source)->issues)
        ->toBe(['Invoice number BL-2026-0921 does not appear '
            .'in the source.']);
});

it('treats unreadable output as a failed extraction', function () {
    InvoiceExtractor::fake([[]]);

    $result = $this->service->extract($this->source);

    expect($result->invoice)->toBeNull()
        ->and($result->needsReview())->toBeTrue();
});

it('prints the extraction from the command', function () {
    InvoiceExtractor::fake([brightlite()]);

    $this->artisan('invoices:extract', [
        'path' => resource_path('samples/invoices/brightlite.txt'),
    ])
        ->expectsOutputToContain('20 x INR 2,150.00')
        ->expectsOutputToContain('Total: INR 48,000.00')
        ->assertSuccessful();
});
