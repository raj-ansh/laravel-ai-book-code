<?php

namespace App\Services\Catalog;

use App\Contracts\Reranker;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Stage 1: a wide, cheap vector search. Stage 2: a slower,
 * smarter reranker picks the best few.
 */
class TwoStageProductSearch
{
    public function __construct(
        private ProductSearch $search,
        private Reranker $reranker,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function search(
        string $query,
        ?int $maxPriceCents = null,
        bool $inStockOnly = false,
        int $limit = 5,
    ): Collection {
        $candidates = $this->search->search(
            $query, $maxPriceCents, $inStockOnly,
            limit: 10, minSimilarity: 0.3,
        );

        $ranked = $this->reranker->rerank(
            $query,
            $candidates->toBase(),
            fn (Product $product) => "{$product->name}: "
                .$product->description,
            $limit,
        );

        // A reranker that rejects every candidate is more likely
        // wrong than the vector search: keep the stage-1 order.
        if ($ranked->isEmpty()) {
            $ranked = $candidates->take($limit);
        }

        return new Collection($ranked->all());
    }
}
