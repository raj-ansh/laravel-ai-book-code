<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find products by name or SKU, with price and stock.')]
#[IsReadOnly]
class CheckStock extends Tool
{
    public function handle(Request $request): Response
    {
        $query = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:50'],
        ])['query'];

        $products = Product::query()
            ->where('sku', $query)
            ->orWhereLike('name', "%{$query}%")
            ->orderBy('name')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return Response::text("No product matches \"{$query}\".");
        }

        return Response::text($products->map(
            fn (Product $product) => "{$product->sku} "
                ."{$product->name}: "
                .Money::inr($product->price_cents)
                .", {$product->stock} in stock",
        )->implode("\n"));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('A name, part of a name, or a SKU.')
                ->required(),
        ];
    }
}
