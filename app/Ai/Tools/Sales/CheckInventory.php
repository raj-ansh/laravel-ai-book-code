<?php

namespace App\Ai\Tools\Sales;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckInventory implements Tool
{
    public function name(): string
    {
        return 'check_inventory';
    }

    public function description(): Stringable|string
    {
        return 'Get how many units are in stock for products '
            .'whose name matches the given words, for example '
            .'"office chair".';
    }

    public function handle(Request $request): Stringable|string
    {
        $words = $request->validate([
            'product' => ['required', 'string', 'min:2', 'max:80'],
        ])['product'];

        // Look up by name: the model cannot invent an
        // identifier it was never given.
        $products = Product::where('name', 'like', "%{$words}%")
            ->orderBy('name')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return "No product name matches \"{$words}\".";
        }

        return $products->map(fn (Product $product) => [
            'sku' => $product->sku,
            'name' => $product->name,
            'units_in_stock' => $product->stock,
        ])->toJson();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'product' => $schema->string()
                ->description('Words from the product name.')
                ->required(),
        ];
    }
}
