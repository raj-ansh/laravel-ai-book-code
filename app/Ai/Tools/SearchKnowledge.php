<?php

namespace App\Ai\Tools;

use App\Services\Knowledge\CitationLedger;
use App\Services\Rag\QuotedAnswerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The knowledge base as a tool: Chapter 34's verified-quote
 * answers, scoped to the signed-in user (Chapter 35).
 */
class SearchKnowledge implements Tool
{
    public function __construct(private CitationLedger $ledger) {}

    public function name(): string
    {
        return 'search_knowledge_base';
    }

    public function description(): Stringable|string
    {
        return 'Search Acme\'s policies, handbooks and the '
            .'customer\'s own documents. Use it for any question '
            .'about rules, terms, procedures or contacts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $question = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ])['question'];

        $this->ledger->searchedFor($question);
        $result = app(QuotedAnswerService::class)->ask($question);

        if (! $result->answered) {
            return 'Nothing in the knowledge base answers this.';
        }

        $this->ledger->found($result->text, $result->sources);

        return "From the knowledge base (verified): {$result->text}";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('The question, in full.')->required(),
        ];
    }
}
