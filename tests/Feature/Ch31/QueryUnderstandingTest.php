<?php

use App\Ai\Agents\SearchQueryParser;
use App\Services\Catalog\ParsedQuery;
use App\Support\RankFusion;

it('fuses rankings, rewarding agreement', function () {
    $fused = RankFusion::reciprocal([
        [10, 20, 30],   // keyword
        [20, 40, 10],   // semantic
    ]);

    expect(array_keys($fused))->toBe([20, 10, 40, 30]);
});

it('turns a request into terms and filters', function () {
    SearchQueryParser::fake([[
        'terms' => 'reading lamp', 'max_price_inr' => 1000,
        'in_stock' => true,
    ]]);

    $query = ParsedQuery::from('a reading lamp under 1000, in stock');

    expect($query)
        ->terms->toBe('reading lamp')
        ->maxPriceCents->toBe(100000)
        ->inStockOnly->toBeTrue();
});

it('ignores filters the request does not mention', function () {
    SearchQueryParser::fake([[
        'terms' => 'walnut shelf', 'max_price_inr' => 5000,
        'in_stock' => true,
    ]]);

    $query = ParsedQuery::from('walnut shelf');

    expect($query->maxPriceCents)->toBeNull()
        ->and($query->inStockOnly)->toBeFalse();
});

it('keeps the request when the terms drift away from it', function () {
    SearchQueryParser::fake([[
        'terms' => 'shoes', 'max_price_inr' => null,
        'in_stock' => false,
    ]]);

    expect(ParsedQuery::from('bamboo rack')->terms)
        ->toBe('bamboo rack');
});

it('never sends product codes to the model', function () {
    SearchQueryParser::fake();

    expect(ParsedQuery::from('SKU-2003')->terms)->toBe('SKU-2003');
    SearchQueryParser::assertNeverPrompted();
});
