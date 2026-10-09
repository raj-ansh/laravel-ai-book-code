<?php

namespace App\Ai\Agents;

use App\Models\Product;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;
use Stringable;

#[MaxSteps(3)]
class ShoppingAssistant implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You help shoppers find Acme products. Search the '
            .'catalogue before recommending anything, and only '
            .'recommend products the search returned.';
    }

    public function tools(): iterable
    {
        return [
            SimilaritySearch::usingModel(
                Product::class, 'embedding',
                minSimilarity: 0.5, limit: 5,
                query: fn ($query) => $query
                    ->select(['name', 'description', 'price_cents'])
                    ->where('stock', '>', 0),
            )->withDescription('Search in-stock products by meaning.'),
        ];
    }
}
