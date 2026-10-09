<?php

use App\Models\Product;
use App\Services\Catalog\ProductEmbedder;
use App\Services\Catalog\ProductSearch;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    // Deterministic 768-d vectors: [light, furniture, garden].
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn (string $text) => array_pad(match (true) {
            str_contains($text, 'lamp'),
            str_contains($text, 'read') => [0.9, 0.2, 0.1],
            str_contains($text, 'desk'),
            str_contains($text, 'table') => [0.2, 0.9, 0.1],
            default => [0.1, 0.1, 0.9],
        }, 768, 0.0),
        $prompt->inputs,
    ));

    Product::factory()->create([
        'name' => 'Brass lamp', 'description' => 'A desk lamp.',
        'price_cents' => 459900, 'stock' => 3,
    ]);
    Product::factory()->create([
        'name' => 'Clip-on lamp', 'description' => 'Small.',
        'price_cents' => 89900, 'stock' => 0,
    ]);
    Product::factory()->create([
        'name' => 'Teak table', 'description' => 'Solid teak.',
        'price_cents' => 1499900, 'stock' => 5,
    ]);

    (new ProductEmbedder)->embedStale();
});

it('embeds only products whose text changed', function () {
    expect(Product::whereNotNull('embedding')->count())->toBe(3)
        ->and((new ProductEmbedder)->embedStale())->toBe(0);

    Product::firstWhere('name', 'Teak table')
        ->update(['description' => 'Solid teak, oiled.']);

    expect((new ProductEmbedder)->embedStale())->toBe(1);
});

it('finds products by meaning, nearest first', function () {
    $names = (new ProductSearch)
        ->search('something to read by')
        ->pluck('name');

    expect($names->all())->toBe(['Brass lamp', 'Clip-on lamp']);
});

it('combines meaning with SQL filters', function () {
    $search = new ProductSearch;

    expect($search->search('reading lamp', inStockOnly: true)
        ->pluck('name')->all())->toBe(['Brass lamp'])
        ->and($search->search('reading lamp', maxPriceCents: 100000)
            ->pluck('name')->all())->toBe(['Clip-on lamp']);
});

it('returns nothing below the similarity threshold', function () {
    expect((new ProductSearch)->search('garden hose'))->toBeEmpty();
});
