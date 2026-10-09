<?php

use App\Models\KnowledgeChunk;
use App\Services\Rag\HandbookIndexer;
use App\Services\Rag\PolicyRetriever;
use App\Services\Text\TextChunker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    // Meaning: [shipping, returns].
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn (string $text) => array_pad(
            preg_match('/ship|deliver/i', $text)
                ? [1.0, 0.1] : [0.1, 1.0],
            768, 0.0,
        ),
        $prompt->inputs,
    ));

    Storage::fake('kb');
    Storage::disk('kb')->put('policies/shipping.md',
        "# Shipping\n\nWe ship in 2 days.\n\nNo shipping abroad.");
    Storage::disk('kb')->put('policies/returns.md',
        "# Returns\n\nReturn within 30 days.");

    $this->indexer = new HandbookIndexer(new TextChunker(30));
});

it('splits documents into embedded chunks', function () {
    expect($this->indexer->index())->toBe(2)
        ->and(KnowledgeChunk::count())->toBe(4)
        ->and($this->indexer->index())->toBe(0);
});

it('re-indexes a changed document, replacing its chunks', function () {
    $this->indexer->index();

    Storage::disk('kb')->put('policies/returns.md',
        "# Returns\n\nReturn within 45 days.");

    expect($this->indexer->index())->toBe(1)
        ->and(KnowledgeChunk::pluck('content'))
        ->toContain('Return within 45 days.')
        ->not->toContain('Return within 30 days.');
});

it('retrieves the chunks closest to the question', function () {
    $this->indexer->index();

    $chunks = (new PolicyRetriever)->retrieve('When will it be delivered?');

    expect($chunks->pluck('document.title')->unique()->all())
        ->toBe(['Shipping']);
});
