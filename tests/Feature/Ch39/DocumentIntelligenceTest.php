<?php

use App\Ai\Agents\ContractTermsExtractor;
use App\Ai\Agents\DocumentTypeClassifier;
use App\Ai\Agents\InvoiceExtractor;
use App\Jobs\ProcessBusinessDocument;
use App\Models\BusinessDocument;
use App\Models\User;
use App\Services\Documents\DocumentProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Storage::fake('local'));

/** A stored document with the given text. */
function documentOf(
    string $text,
    string $name = 'doc.txt',
): BusinessDocument {
    Storage::disk('local')->put("business-documents/{$name}", $text);

    return BusinessDocument::create([
        'filename' => $name, 'path' => "business-documents/{$name}",
        'mime' => 'text/plain', 'status' => 'pending',
    ]);
}

$terms = 'Acme pays approved invoices within 45 days. Either party '
    .'may end the agreement with 90 days notice. Deliveries more '
    .'than seven days late incur a penalty of 2% per week, up to 10%.';

it('extracts grounded contract terms', function () use ($terms) {
    DocumentTypeClassifier::fake([
        ['reason' => 'Terms.', 'type' => 'contract'],
    ]);
    ContractTermsExtractor::fake([[
        'payment_days' => 45, 'notice_days' => 90,
        'late_penalty_percent' => 2, 'late_penalty_cap_percent' => 10,
    ]]);

    $document = app(DocumentProcessor::class)
        ->process(documentOf($terms));

    expect($document)
        ->type->toBe('contract')
        ->status->toBe('processed')
        ->issues->toBe([]);
});

it('flags terms the contract does not contain', function () use ($terms) {
    DocumentTypeClassifier::fake([
        ['reason' => 'Terms.', 'type' => 'contract'],
    ]);
    ContractTermsExtractor::fake([[
        'payment_days' => 30, 'notice_days' => 90,
        'late_penalty_percent' => 2, 'late_penalty_cap_percent' => null,
    ]]);

    $document = app(DocumentProcessor::class)
        ->process(documentOf($terms));

    expect($document->status)->toBe('needs_review')
        ->and($document->issues)->toBe([
            'payment_days = 30 is not in the text.',
            'The contract does not state late_penalty_cap_percent.',
        ]);
});

it('catches an invoice invented from a misclassified report', function () {
    DocumentTypeClassifier::fake([
        ['reason' => 'Has amounts.', 'type' => 'invoice'],
    ]);
    InvoiceExtractor::fake([[
        'supplier' => 'Acme Support', 'invoice_number' => 'INV3Q2023',
        'invoice_date' => '2023-09-30', 'currency' => 'INR',
        'lines' => [['description' => 'Support', 'quantity' => 1,
            'unit_price' => 1700]],
        'total' => 2000,
    ]]);

    $document = app(DocumentProcessor::class)->process(documentOf(
        'Quarterly support review. Tickets rose 15 percent to 18,420.'
    ));

    expect($document->status)->toBe('needs_review')
        ->and($document->issues)->toContain(
            'Invoice number INV3Q2023 does not appear in the source.'
        );
});

it('sends other documents and unreadable ones to a person', function () {
    DocumentTypeClassifier::fake([
        ['reason' => 'A guide.', 'type' => 'other'],
    ]);
    $processor = app(DocumentProcessor::class);

    $guide = $processor->process(documentOf(
        'Lamp care guide. Wipe brass with a soft dry cloth, never polish.'
    ));
    $empty = $processor->process(documentOf('Hi.', 'empty.txt'));

    expect($guide->status)->toBe('needs_review')
        ->and($guide->type)->toBe('other')
        ->and($empty->issues)
        ->toBe(['No text could be read from the document.']);
    DocumentTypeClassifier::assertPromptedTimes(1);
});

it('accepts uploads from staff and lists the review queue', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->staff()->create());

    $this->postJson('/api/staff/documents', [
        'file' => UploadedFile::fake()
            ->createWithContent('terms.txt', 'x'),
    ])->assertAccepted();
    Queue::assertPushed(ProcessBusinessDocument::class);

    documentOf('x')->update(['status' => 'needs_review']);
    $this->getJson('/api/staff/documents/review')->assertJsonCount(1);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/staff/documents/review')->assertForbidden();
});
