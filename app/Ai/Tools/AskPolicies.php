<?php

namespace App\Ai\Tools;

use App\Services\Rag\QuotedAnswerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Acme's policies as a tool: answers come from the RAG
 * pipeline of Part VI, with verified quotes, scoped to the
 * signed-in user.
 */
class AskPolicies implements Tool
{
    public function name(): string
    {
        return 'ask_policies';
    }

    public function description(): Stringable|string
    {
        return 'Answer a question about Acme\'s policies: returns, '
            .'refunds, delivery, warranty, payment. Pass the '
            .'customer\'s question as a complete sentence.';
    }

    public function handle(Request $request): Stringable|string
    {
        $question = trim($request->string('question'));

        if ($question === '') {
            return 'Ask a question about a policy.';
        }

        $answer = app(QuotedAnswerService::class)->ask($question);

        if (! $answer->answered) {
            return 'The policies do not answer this. Do not guess; '
                .'offer to pass the question to a person.';
        }

        return $answer->text."\nSources: ".implode(', ', array_unique(
            array_column($answer->sources, 'title')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->required(),
        ];
    }
}
