<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\Rag\OverlappingChunker;
use App\Services\Rag\TextExtractor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

/**
 * Extract, chunk, embed, store, with the document's status
 * updated at every stage.
 */
class IngestDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 600;

    public function __construct(public KnowledgeDocument $document) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    public function handle(
        TextExtractor $extractor,
        OverlappingChunker $chunker,
    ): void {
        $this->document->update(['status' => 'extracting']);

        $text = $extractor->extract(
            $this->document->path, $this->document->mime,
        );

        // Scanned PDFs have no text layer: nothing to index.
        if (mb_strlen($text) < 50) {
            throw new RuntimeException('No text could be extracted.');
        }

        $chunks = $chunker->split($text);
        $this->document->update(['status' => 'embedding']);

        $prefix = config('acme.ai.embeddings.prefixes.document');
        $vectors = [];

        foreach (array_chunk($chunks, 50) as $batch) {
            array_push($vectors, ...Embeddings::for(array_map(
                fn (string $chunk) => $prefix.$chunk, $batch,
            ))->generate()->embeddings);
        }

        DB::transaction(function () use ($chunks, $vectors) {
            $this->document->chunks()->delete();

            foreach ($chunks as $position => $content) {
                $this->document->chunks()->create([
                    'position' => $position,
                    'content' => $content,
                    'embedding' => $vectors[$position],
                ]);
            }

            $this->document->update([
                'status' => 'ready',
                'chunk_count' => count($chunks),
                'error' => null,
            ]);
        });
    }

    public function failed(?Throwable $e): void
    {
        $this->document->update([
            'status' => 'failed',
            'error' => $e?->getMessage(),
        ]);
    }
}
