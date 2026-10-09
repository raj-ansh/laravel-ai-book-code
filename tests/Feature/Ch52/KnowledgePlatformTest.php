<?php

use App\Ai\Agents\KnowledgeAssistant;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Knowledge\CitationLedger;
use App\Services\Knowledge\KnowledgePlatform;
use App\Services\Rag\QuotedAnswerService;
use App\Services\Rag\RagAnswer;
use App\Ai\Agents\QuestionRewriter;
use App\Services\Rag\PolicyRetriever;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Responses\Data\ToolCall;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->priya = User::factory()->create([
        'name' => 'Priya Nair',
        'organisation_id' => Organisation::create([
            'name' => 'Brightlite', 'ai_plan' => 'pro'])->id,
    ]);
});

/** The knowledge base answers with these verified sources. */
function knowledgeBase(?string $answer): void
{
    test()->mock(QuotedAnswerService::class)
        ->shouldReceive('ask')
        ->andReturnUsing(function () use ($answer) {
            return $answer === null
                ? new RagAnswer('Not found.', [], answered: false)
                : new RagAnswer($answer, [[
                    'number' => 1, 'title' => 'Brightlite trade terms',
                    'content' => 'Orders above INR 5,00,000 receive '
                        .'a trade discount of 8%.',
                ]], answered: true, cited: true);
        });
}

function searchCall(string $question): ToolCall
{
    return new ToolCall('call_1', 'search_knowledge_base', [
        'question' => $question,
    ]);
}

it('cites what the search found, not what the model says', function () {
    knowledgeBase('8% on orders above INR 5,00,000.');
    KnowledgeAssistant::fake([
        searchCall('trade discount'),
        'You get 8% off orders above INR 5,00,000 [source 7].',
    ]);

    $answer = app(KnowledgePlatform::class)
        ->ask($this->priya, 'Do we get a discount?');

    expect($answer)
        ->route->toBe('agent')
        ->searches->toBe(['trade discount'])
        ->and($answer->citations[0]['title'])
        ->toBe('Brightlite trade terms');
});

it('searches itself when the agent answers from memory', function () {
    knowledgeBase('8% on orders above INR 5,00,000.');
    KnowledgeAssistant::fake(['Discounts are 15% for everyone.']);

    $answer = app(KnowledgePlatform::class)
        ->ask($this->priya, 'Do we get a discount?');

    expect($answer)
        ->route->toBe('fallback')
        ->reply->toBe('8% on orders above INR 5,00,000.')
        ->searches->toBe([]);
});

it('never shows an unsourced reply', function () {
    knowledgeBase(null);
    KnowledgeAssistant::fake([
        searchCall('capital of France'),
        'The capital of France is Paris.',
    ]);

    $answer = app(KnowledgePlatform::class)
        ->ask($this->priya, 'What is the capital of France?');

    expect($answer)
        ->route->toBe('none')
        ->reply->toBe(KnowledgePlatform::NOT_COVERED)
        ->citations->toBe([]);
});

it('answers company orders from the order tool', function () {
    knowledgeBase(null);
    Order::factory()->for($this->priya)->create(['number' => 'A-3001']);
    KnowledgeAssistant::fake([
        new ToolCall('call_1', 'get_company_order', [
            'order_number' => 'A-3001',
        ]),
        'Order A-3001 has shipped.',
    ]);

    expect(app(KnowledgePlatform::class)
        ->ask($this->priya, 'Status of A-3001?'))
        ->route->toBe('agent')
        ->reply->toBe('Order A-3001 has shipped.');
});

it('builds the fallback retriever for the asker', function () {
    // The real services, with a retriever that records who it
    // was built for and finds nothing.
    $builtFor = [];
    app()->bind(PolicyRetriever::class, function ($app) use (
        &$builtFor,
    ) {
        $builtFor[] = $app['auth']->id();

        return new class extends PolicyRetriever
        {
            public function retrieve(
                string $question,
                int $limit = 4,
                float $minSimilarity = 0.5,
            ): Collection {
                return new Collection;
            }
        };
    });
    QuestionRewriter::fake([['standalone' => 'Q?', 'variants' => []]]);
    KnowledgeAssistant::fake(['From memory.']);

    app(KnowledgePlatform::class)->ask($this->priya, 'Question?');

    // Built after sign-in, so scoped to Priya, not to nobody.
    expect($builtFor)->toBe([$this->priya->id]);
});

it('gives the order tool only to people in a company', function () {
    $tools = fn (User $user) => collect(
        (new KnowledgeAssistant($user, new CitationLedger))->tools(),
    )->map->name()->all();

    expect($tools($this->priya))
        ->toBe(['search_knowledge_base', 'get_company_order'])
        ->and($tools(User::factory()->create()))
        ->toBe(['search_knowledge_base']);
});

it('refuses someone else\'s conversation', function () {
    knowledgeBase('8% on orders above INR 5,00,000.');
    KnowledgeAssistant::fake([searchCall('discount'), 'Answer.']);
    $first = app(KnowledgePlatform::class)
        ->ask($this->priya, 'Do we get a discount?');

    app(KnowledgePlatform::class)->ask(User::factory()->create(),
        'And for small orders?', $first->conversation_id);
})->throws(NotFoundHttpException::class);
