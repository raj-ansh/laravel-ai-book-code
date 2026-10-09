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
#[MaxTokens(250)]
class QuestionRewriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You prepare questions for a search of Acme's policies.

            - standalone: the customer's latest question rewritten
              so it makes sense without the earlier conversation.
              Keep the customer's meaning; add nothing new.
            - variants: two other ways to ask the same question,
              using different words.
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'standalone' => $schema->string()->required(),
            'variants' => $schema->array()
                ->items($schema->string())->max(3)->required(),
        ];
    }
}
