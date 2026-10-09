<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\HybridProductSearch;
use App\Services\Catalog\ParsedQuery;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:find {request}')]
#[Description('Understand a shopping request and search for it')]
class FindProducts extends Command
{
    public function handle(HybridProductSearch $search): int
    {
        $query = ParsedQuery::from($this->argument('request'));

        $this->line(sprintf('terms: "%s", max price: %s, in stock: %s',
            $query->terms,
            $query->maxPriceCents
                ? Money::inr($query->maxPriceCents) : '-',
            $query->inStockOnly ? 'yes' : 'no'));

        $products = $search->search(
            $query->terms, $query->maxPriceCents, $query->inStockOnly,
        );

        foreach ($products as $product) {
            $this->line("  {$product->name}, "
                .Money::inr($product->price_cents)
                .", {$product->stock} in stock");
        }

        return self::SUCCESS;
    }
}
