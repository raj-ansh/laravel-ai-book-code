<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.0)]
#[MaxTokens(600)]
#[Timeout(120)]
class RelevanceJudge implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You judge search results. For every numbered result,
            score how well it answers the shopper's query, from
            0 (irrelevant) to 10 (exactly what they want).
            Score every result, using its number as the index.
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scores' => $schema->array()->items($schema->object([
                'index' => $schema->integer()->required(),
                'score' => $schema->integer()->min(0)->max(10)
                    ->required(),
            ]))->required(),
        ];
    }
}
