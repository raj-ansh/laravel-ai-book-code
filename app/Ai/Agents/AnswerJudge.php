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
#[MaxTokens(200)]
class AnswerJudge implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You check answers written by another assistant.

            supported: true only if every claim in the answer is
            stated in the sources. An answer that concludes
            something the sources do not say ("there is no fee"
            when the sources do not mention fees) is NOT supported.
            reason: one sentence.
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->required(),
            'supported' => $schema->boolean()->required(),
        ];
    }
}
