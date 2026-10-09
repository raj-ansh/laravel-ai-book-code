<?php

namespace App\Services\Knowledge;

use App\Ai\Agents\KnowledgeAssistant;
use App\Models\KnowledgeAnswer;
use App\Models\User;
use App\Services\Rag\PolicyQuestionService;
use App\Services\Rag\QuotedAnswerService;
use App\Services\Support\ConversationGuard;
use App\Support\AiContext;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * One question in, one recorded answer out. The agent goes
 * first; code makes sure the answer rests on the knowledge
 * base, and backs it up when the agent did not search.
 */
class KnowledgePlatform
{
    public const NOT_COVERED = 'The knowledge base does not '
        .'cover that. support@acme.test can help.';

    public function __construct(private ConversationGuard $guard) {}

    public function ask(
        User $user,
        string $question,
        ?string $conversationId = null,
    ): KnowledgeAnswer {
        $this->guard->ensureOwnedBy($conversationId, $user);

        // Retrieval is scoped to the signed-in user (Ch. 35);
        // commands and jobs have to say who that is.
        Auth::setUser($user);
        AiContext::forUser($user);

        $ledger = new CitationLedger;
        $response = KnowledgeAssistant::make(
            user: $user, ledger: $ledger,
        )->continueOrStart($conversationId, as: $user)
            ->prompt($question);

        $tools = $response->toolResults
            ->map(fn (ToolResult $r) => $r->name)->unique();

        [$reply, $route, $citations] = match (true) {
            // The agent searched and found verified sources.
            $ledger->hasAnswer() => [$response->text, 'agent',
                $ledger->citations()],
            // It answered from a company order.
            $tools->contains('get_company_order')
                => [$response->text, 'agent', []],
            // No verified answer: the agent did not search, or
            // searched with words that found nothing. Search
            // once more with the person's own question. The
            // agent's reply is never used without sources.
            default => $this->fallback($question),
        };

        return KnowledgeAnswer::create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'conversation_id' => $response->conversationId,
            'question' => $question,
            'reply' => $reply,
            'citations' => $citations,
            'route' => $route,
            'trace_id' => $response->invocationId,
            'searches' => $ledger->queries(),
        ]);
    }

    /**
     * @return array{string, string, list<array<string, string>>}
     */
    private function fallback(string $question): array
    {
        $ledger = new CitationLedger;

        // Resolved here, after Auth::setUser(): the retriever
        // inside is scoped to whoever is signed in when it is
        // built. Injected in the constructor, it was built for
        // nobody, and searched public documents only.
        $result = app(QuotedAnswerService::class)->ask($question);

        if (! $result->answered
            || $result->text === PolicyQuestionService::NOT_FOUND) {
            return [self::NOT_COVERED, 'none', []];
        }

        $ledger->found($result->text, $result->sources);

        return [$result->text, 'fallback', $ledger->citations()];
    }
}
