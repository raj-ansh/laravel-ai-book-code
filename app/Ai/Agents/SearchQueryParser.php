<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.0)]
#[MaxTokens(150)]
class SearchQueryParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You turn a shopper's request into a product search.

            - terms: what they are looking for, in a few words,
              without prices or stock conditions.
            - max_price_inr: a price limit in rupees if they give
              one ("under 1500", "below Rs 2,000"), else null.
            - in_stock: true only if they ask for items available
              now ("in stock", "available today").
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'terms' => $schema->string()->required(),
            'max_price_inr' => $schema->integer()
                ->nullable()->required(),
            'in_stock' => $schema->boolean()->required(),
        ];
    }
}
