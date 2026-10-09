<?php

use App\Ai\Agents\ShoppingAssistant;
use App\Models\Product;
use App\Services\Catalog\HybridProductSearch;
use App\Services\Catalog\ProductEmbedder;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    // Meaning: [light, furniture]. Codes mean nothing.
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn (string $text) => array_pad(
            preg_match('/lamp|light|glow/i', $text)
                ? [1.0, 0.1] : [0.1, 1.0],
            768, 0.0,
        ),
        $prompt->inputs,
    ));

    Product::factory()->create([
        'sku' => 'SKU-2003', 'name' => 'Smart bulb',
        'description' => 'Wi-Fi bulb.', 'stock' => 10,
    ]);
    Product::factory()->create([
        'sku' => 'SKU-1001', 'name' => 'Brass lamp',
        'description' => 'Warm light for reading.', 'stock' => 10,
    ]);
    Product::factory()->create([
        'sku' => 'SKU-1003', 'name' => 'Walnut bookshelf',
        'description' => 'Five shelves.', 'stock' => 10,
    ]);

    (new ProductEmbedder)->embedStale();
});

it('finds exact codes through keywords', function () {
    expect(app(HybridProductSearch::class)->search('SKU-2003')
        ->first()->name)->toBe('Smart bulb');
});

it('finds meaning when no keyword matches', function () {
    $names = app(HybridProductSearch::class)
        ->search('something that glows')->pluck('name');

    expect($names->first())->toBe('Brass lamp');
});

it('puts results found both ways first', function () {
    $names = app(HybridProductSearch::class)
        ->search('reading lamp')->pluck('name');

    expect($names->first())->toBe('Brass lamp');
});

it('lets an agent search the catalogue itself', function () {
    ShoppingAssistant::fake([
        new ToolCall('call_1', 'SimilaritySearch', [
            'query' => 'a lamp for reading',
        ]),
        'The Brass lamp would suit you.',
    ]);

    $response = ShoppingAssistant::make()
        ->prompt('I need light to read by.');

    expect($response->toolResults->first()->text())
        ->toContain('Brass lamp')
        ->not->toContain('Walnut bookshelf');
});
