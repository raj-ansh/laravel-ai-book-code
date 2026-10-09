<?php

use App\Services\Rag\OverlappingChunker;
use App\Services\Rag\TextExtractor;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('rebuilds paragraphs and headings from a PDF', function () {
    Storage::disk('local')->put('terms.pdf', file_get_contents(
        resource_path('samples/docs/supplier-terms.pdf')
    ));

    $text = (new TextExtractor)->extract('terms.pdf', 'application/pdf');

    expect($text)
        ->toContain("\n\n2. Delivery\n\n")
        ->toContain('working days, stating the delivery date');
});

it('keeps only the content of an HTML page', function () {
    Storage::disk('local')->put('care.html', file_get_contents(
        resource_path('samples/docs/lamp-care.html')
    ));

    $text = (new TextExtractor)->extract('care.html', 'text/html');

    expect($text)
        ->toStartWith("Lamp care guide\n\nSwitch off and unplug")
        ->not->toContain('Home | Shop')     // nav
        ->not->toContain('var x')           // script
        ->not->toContain('Acme 2026');      // footer
});

it('refuses types it cannot read', function () {
    Storage::disk('local')->put('a.bin', 'x');

    (new TextExtractor)->extract('a.bin', 'application/zip');
})->throws(InvalidArgumentException::class);

it('repeats the last sentence at the start of the next chunk', function () {
    $chunks = (new OverlappingChunker(maxChars: 40, overlap: 1))
        ->split('First one here. Second one here. Third one here.');

    expect($chunks)->toBe([
        'First one here. Second one here.',
        'Second one here. Third one here.',
    ]);
});

it('does not treat section numbers as sentence ends', function () {
    $chunks = (new OverlappingChunker(maxChars: 30, overlap: 1))
        ->split("Pay in 45 days.\n\n4. Invoices are monthly.");

    expect($chunks)->toBe([
        'Pay in 45 days.',
        'Pay in 45 days. 4. Invoices are monthly.',
    ]);
});
