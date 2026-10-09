<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

/**
 * Keeps product embeddings in step with product text.
 */
class ProductEmbedder
{
    /**
     * Embed every product whose text changed since it was
     * last embedded. Returns how many were embedded.
     */
    public function embedStale(int $batchSize = 50): int
    {
        $count = 0;

        Product::query()->orderBy('id')->chunkById(
            $batchSize,
            function (Collection $products) use (&$count) {
                $stale = $products->reject(
                    fn (Product $product) => $product->embedding_hash
                        === sha1($product->embeddingText()),
                )->values();

                if ($stale->isNotEmpty()) {
                    $this->embed($stale);
                    $count += $stale->count();
                }
            },
        );

        return $count;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function embed(Collection $products): void
    {
        $prefix = config('acme.ai.embeddings.prefixes.document');

        $response = Embeddings::for($products->map(
            fn (Product $product) => $prefix.$product->embeddingText(),
        )->all())->generate();

        foreach ($products as $i => $product) {
            $product->update([
                'embedding' => $response->embeddings[$i],
                'embedding_model' => $response->meta->model,
                'embedding_hash' => sha1($product->embeddingText()),
            ]);
        }
    }
}
