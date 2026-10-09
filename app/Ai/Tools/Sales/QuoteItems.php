<?php

namespace App\Ai\Tools\Sales;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * The "items" argument shared by the quote tools.
 */
trait QuoteItems
{
    /**
     * @return list<array{sku: string, quantity: int}>
     */
    protected function validatedItems(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.sku' => ['required', 'regex:/^SKU-\d{4}$/'],
            'items.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
        ])['items'];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'sku' => $schema->string()->required(),
                    'quantity' => $schema->integer()->min(1)
                        ->required(),
                ])
            )->min(1)->required(),
        ];
    }
}
