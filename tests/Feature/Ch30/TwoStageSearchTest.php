<?php

use App\Ai\Agents\RelevanceJudge;
use App\Models\Product;
use App\Services\Catalog\ProductEmbedder;
use App\Services\Catalog\TwoStageProductSearch;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    // Every text gets the same vector: stage 1 keeps them all.
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn () => array_pad([1.0], 768, 0.0), $prompt->inputs,
    ));

    foreach (['Bookshelf', 'Garden lights', 'Night light'] as $name) {
        Product::factory()->create(['name' => $name]);
    }

    (new ProductEmbedder)->embedStale();
});

it('lets the reranker reorder the vector candidates', function () {
    RelevanceJudge::fake([['scores' => [
        ['index' => 0, 'score' => 0],
        ['index' => 1, 'score' => 9],
        ['index' => 2, 'score' => 7],
    ]]]);

    $names = app(TwoStageProductSearch::class)
        ->search('balcony at night')->pluck('name');

    expect($names->all())->toBe(['Garden lights', 'Night light']);
});

it('falls back to vector order if the reranker rejects all', function () {
    RelevanceJudge::fake([['scores' => [
        ['index' => 0, 'score' => 0],
        ['index' => 1, 'score' => 1],
        ['index' => 2, 'score' => 2],
    ]]]);

    expect(app(TwoStageProductSearch::class)
        ->search('balcony at night', limit: 2))->toHaveCount(2);
});
