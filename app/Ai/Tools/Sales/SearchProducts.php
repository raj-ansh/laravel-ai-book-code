<?php

namespace App\Ai\Tools\Sales;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProducts implements Tool
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): Stringable|string
    {
        return 'Find products by words in their name or '
            .'description. Returns up to 5 products with SKU, '
            .'price and whether they are in stock.';
    }

    public function handle(Request $request): Stringable|string
    {
        $words = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:80'],
        ])['query'];

        $products = Product::query()
            ->where('name', 'like', "%{$words}%")
            ->orWhere('description', 'like', "%{$words}%")
            ->orderBy('name')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return "No products match \"{$words}\".";
        }

        return $products->map(fn (Product $product) => [
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => Money::inr($product->price_cents),
            'in_stock' => $product->stock > 0,
        ])->toJson();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Words to search for.')
                ->required(),
        ];
    }
}
