<?php

namespace App\Services\Rag;

use App\Models\KnowledgeDocument;
use App\Services\Text\TextChunker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

/**
 * Indexes the handbook: each policy file becomes a document,
 * split into chunks, each chunk embedded.
 */
class HandbookIndexer
{
    public function __construct(
        private TextChunker $chunker = new TextChunker(600),
    ) {}

    /**
     * @return int How many documents were (re)indexed.
     */
    public function index(): int
    {
        $disk = Storage::disk('kb');
        $indexed = 0;

        foreach ($disk->files('policies') as $path) {
            $text = $disk->get($path);

            if (KnowledgeDocument::where('source', "kb:{$path}")
                ->where('hash', sha1($text))->exists()) {
                continue;
            }

            $this->indexDocument("kb:{$path}", $text);
            $indexed++;
        }

        return $indexed;
    }

    private function indexDocument(string $source, string $text): void
    {
        $chunks = $this->chunker->split($text);
        $prefix = config('acme.ai.embeddings.prefixes.document');

        $vectors = Embeddings::for(array_map(
            fn (string $chunk) => $prefix.$chunk, $chunks,
        ))->generate()->embeddings;

        DB::transaction(function () use ($source, $text, $chunks, $vectors) {
            $document = KnowledgeDocument::updateOrCreate(
                ['source' => $source],
                ['title' => $this->title($text), 'hash' => sha1($text)],
            );

            // Replace all chunks: positions may have shifted.
            $document->chunks()->delete();

            foreach ($chunks as $position => $content) {
                $document->chunks()->create([
                    'position' => $position,
                    'content' => $content,
                    'embedding' => $vectors[$position],
                ]);
            }
        });
    }

    private function title(string $text): string
    {
        return preg_match('/^#\s+(.+)$/m', $text, $match)
            ? trim($match[1]) : 'Untitled';
    }
}
