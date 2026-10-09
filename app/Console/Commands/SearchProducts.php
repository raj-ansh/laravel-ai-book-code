<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use App\Services\Catalog\TwoStageProductSearch;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:search {query} {--max-price=} {--in-stock}
    {--rerank : Rerank the candidates (Chapter 30)}')]
#[Description('Search products by meaning')]
class SearchProducts extends Command
{
    public function handle(
        ProductSearch $search,
        TwoStageProductSearch $twoStage,
    ): int {
        $maxPrice = $this->option('max-price');
        $arguments = [
            $this->argument('query'),
            $maxPrice ? (int) $maxPrice * 100 : null,
            (bool) $this->option('in-stock'),
        ];

        $products = $this->option('rerank')
            ? $twoStage->search(...$arguments)
            : $search->search(...$arguments);

        $this->table(
            ['Similarity', 'Product', 'Price', 'Stock'],
            $products->map(fn (Product $product) => [
                number_format(1 - $product->distance, 3),
                $product->name,
                Money::inr($product->price_cents),
                $product->stock,
            ]),
        );

        return self::SUCCESS;
    }
}
