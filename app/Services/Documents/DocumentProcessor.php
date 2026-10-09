<?php

namespace App\Services\Documents;

use App\Ai\Agents\ContractTermsExtractor;
use App\Ai\Agents\DocumentTypeClassifier;
use App\Models\BusinessDocument;
use App\Services\Invoices\InvoiceExtractionService;
use App\Services\Rag\TextExtractor;
use Illuminate\Support\Str;

/**
 * Read, classify, extract, check: one document at a time.
 * Every step can send the document to a person.
 */
class DocumentProcessor
{
    public function __construct(
        private TextExtractor $text,
        private InvoiceExtractionService $invoices,
    ) {}

    public function process(
        BusinessDocument $document,
    ): BusinessDocument {
        $text = $this->text->extract($document->path, $document->mime);

        if (mb_strlen($text) < 50) {
            return $this->finish($document, null, [],
                ['No text could be read from the document.']);
        }

        // Classify on the opening, where the type is clear.
        $label = DocumentTypeClassifier::make()->prompt(
            Str::limit($text, 3000)
        )['type'] ?? null;
        $type = in_array($label, DocumentTypeClassifier::TYPES, true)
            ? $label : 'other';

        return match ($type) {
            'invoice' => $this->invoice($document, $text),
            'contract' => $this->contract($document, $text),
            default => $this->finish($document, 'other', [],
                ['Not an invoice or a contract: route by hand.']),
        };
    }

    private function invoice(
        BusinessDocument $document,
        string $text,
    ): BusinessDocument {
        $result = $this->invoices->extract($text);
        $invoice = $result->invoice;

        return $this->finish($document, 'invoice', $invoice ? [
            'supplier' => $invoice->supplier,
            'number' => $invoice->number,
            'date' => $invoice->date?->toDateString(),
            'total_cents' => $invoice->totalCents,
        ] : [], $result->issues);
    }

    private function contract(
        BusinessDocument $document,
        string $text,
    ): BusinessDocument {
        $terms = ContractTermsExtractor::make()->prompt($text)
            ->toArray();
        $issues = [];

        // Every number must appear in the contract (Chapter 21).
        foreach ($terms as $term => $value) {
            if ($value === null) {
                $issues[] = "The contract does not state {$term}.";
            } elseif (! $this->appears($value, $text)) {
                $issues[] = "{$term} = {$value} is not in the text.";
            }
        }

        return $this->finish($document, 'contract', $terms, $issues);
    }

    /**
     * Whether a number appears in the text as a whole number:
     * 45 matches "45 days", not "450" or "1.45".
     */
    private function appears(int|float $value, string $text): bool
    {
        $number = preg_quote((string) (0 + $value), '/');

        return preg_match("/(?<![\\d.]){$number}(?![\\d])/", $text) === 1;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $issues
     */
    private function finish(
        BusinessDocument $document,
        ?string $type,
        array $fields,
        array $issues,
    ): BusinessDocument {
        $document->update([
            'type' => $type,
            'fields' => $fields,
            'issues' => $issues,
            'status' => $issues === [] && $fields !== []
                ? 'processed' : 'needs_review',
        ]);

        return $document;
    }
}
