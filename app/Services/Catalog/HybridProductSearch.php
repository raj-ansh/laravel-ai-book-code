<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Support\RankFusion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keyword search and vector search, run separately and
 * fused: exact words and meaning both count.
 */
class HybridProductSearch
{
    public function __construct(private ProductSearch $semantic) {}

    /**
     * @return Collection<int, Product>
     */
    public function search(
        string $query,
        ?int $maxPriceCents = null,
        bool $inStockOnly = false,
        int $limit = 5,
    ): Collection {
        $keyword = $this->keyword($query, $maxPriceCents, $inStockOnly)
            ->pluck('id')->all();

        $semantic = $this->semantic->search(
            $query, $maxPriceCents, $inStockOnly,
            limit: 20, minSimilarity: 0.4,
        )->pluck('id')->all();

        $ids = array_slice(array_keys(
            RankFusion::reciprocal([$keyword, $semantic])
        ), 0, $limit);

        return Product::findMany($ids)
            ->sortBy(fn (Product $p) => array_search($p->id, $ids))
            ->values();
    }

    /**
     * @return Builder<Product>
     */
    private function keyword(
        string $query,
        ?int $maxPriceCents,
        bool $inStockOnly,
    ): Builder {
        $match = "websearch_to_tsquery('english', ?)";

        return Product::query()
            ->whereRaw("search_vector @@ {$match}", [$query])
            ->when($maxPriceCents, fn ($q, $max) => $q
                ->where('price_cents', '<=', $max))
            ->when($inStockOnly, fn ($q) => $q->where('stock', '>', 0))
            ->orderByRaw("ts_rank(search_vector, {$match}) desc", [$query])
            ->limit(20);
    }
}
