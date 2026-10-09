<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Embeddings;

/**
 * Search products by meaning, combined with ordinary SQL
 * filters, in one PostgreSQL query.
 */
class ProductSearch
{
    /**
     * @return Collection<int, Product>
     */
    public function search(
        string $query,
        ?int $maxPriceCents = null,
        bool $inStockOnly = false,
        int $limit = 5,
        float $minSimilarity = 0.5,
    ): Collection {
        $prefix = config('acme.ai.embeddings.prefixes.query');

        $vector = Embeddings::for([$prefix.$query])
            ->cache()
            ->generate()
            ->first();

        return Product::query()
            ->select([
                'id', 'sku', 'name', 'description', 'price_cents',
                'stock',
            ])
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->whereNotNull('embedding')
            ->when($maxPriceCents, fn ($q, $max) => $q
                ->where('price_cents', '<=', $max))
            ->when($inStockOnly, fn ($q) => $q->where('stock', '>', 0))
            ->whereVectorSimilarTo('embedding', $vector, $minSimilarity)
            ->limit($limit)
            ->get();
    }
}
