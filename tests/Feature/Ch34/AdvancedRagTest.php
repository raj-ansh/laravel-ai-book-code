<?php

use App\Ai\Agents\QuestionRewriter;
use App\Ai\Agents\QuotingAnswerer;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Rag\MultiQueryRetriever;
use App\Services\Rag\PolicyRetriever;
use App\Services\Rag\QuotedAnswerService;
use Illuminate\Database\Eloquent\Collection;

/** A chunk in memory, with an id and a document title. */
function chunk(int $id, string $title, string $content): KnowledgeChunk
{
    return (new KnowledgeChunk)
        ->forceFill(['id' => $id, 'content' => $content])
        ->setRelation('document',
            new KnowledgeDocument(['title' => $title]));
}

beforeEach(function () {
    $this->queries = [];
    $this->results = [];
    $test = $this;

    // Returns $this->results[$query] and records each query.
    app()->instance(PolicyRetriever::class, new class($test)
        extends PolicyRetriever {
        public function __construct(private $test) {}

        public function retrieve(string $question, int $limit = 4,
            float $minSimilarity = 0.5): Collection
        {
            $this->test->queries[] = $question;

            return new Collection(
                $this->test->results[$question] ?? []
            );
        }
    });

    $this->warranty = chunk(1, 'Warranty',
        'Furniture carries a 2-year warranty.');
    $this->lamps = chunk(2, 'Warranty',
        'Lamps and lighting carry a 1-year warranty.');
});

it('rewrites a follow-up into a standalone question', function () {
    QuestionRewriter::fake([[
        'standalone' => 'How long is the furniture warranty?',
        'variants' => ['Furniture guarantee period'],
    ]]);

    $result = app(MultiQueryRetriever::class)->retrieve(
        'And for furniture?', 'How long is the warranty on lamps?',
    );

    expect($result['question'])
        ->toBe('How long is the furniture warranty?')
        ->and($this->queries)->toBe([
            'And for furniture?',
            'How long is the furniture warranty?',
            'Furniture guarantee period',
        ]);
    QuestionRewriter::assertPrompted(fn ($prompt) =>
        $prompt->contains('Earlier question: How long is the warranty')
        && $prompt->contains('Latest question: And for furniture?'));
});

it('fuses the results of every phrasing', function () {
    QuestionRewriter::fake([[
        'standalone' => 'Furniture warranty?', 'variants' => [],
    ]]);
    $this->results = [
        'And for furniture?' => [$this->lamps, $this->warranty],
        'Furniture warranty?' => [$this->warranty],
    ];

    $chunks = app(MultiQueryRetriever::class)
        ->retrieve('And for furniture?')['chunks'];

    expect($chunks->pluck('id')->all())->toBe([1, 2]);
});

it('answers only with quotes found in the sources', function () {
    QuestionRewriter::fake([['standalone' => 'Q', 'variants' => []]]);
    $this->results = ['Q' => [$this->lamps, $this->warranty]];
    QuotingAnswerer::fake([[
        'quotes' => [[
            // Wrong number: the sentence is in source 2.
            'source' => 1,
            'text' => 'Furniture carries a 2-year warranty.',
        ]],
        'answer' => 'Two years.',
    ]]);

    $answer = app(QuotedAnswerService::class)->ask('Q');

    expect($answer->answered)->toBeTrue()
        ->and($answer->text)->toBe('Two years.')
        ->and(array_column($answer->sources, 'number'))->toBe([2]);
});

it('refuses when a quote is not word for word', function () {
    QuestionRewriter::fake([['standalone' => 'Q', 'variants' => []]]);
    $this->results = ['Q' => [$this->warranty]];
    QuotingAnswerer::fake([[
        'quotes' => [[
            'source' => 1,
            'text' => 'Furniture is guaranteed for two years.',
        ]],
        'answer' => 'Two years.',
    ]]);

    expect(app(QuotedAnswerService::class)->ask('Q')->answered)
        ->toBeFalse();
});

it('refuses an answer without quotes', function () {
    QuestionRewriter::fake([['standalone' => 'Q', 'variants' => []]]);
    $this->results = ['Q' => [$this->warranty]];
    QuotingAnswerer::fake([['quotes' => [], 'answer' => 'Yes.']]);

    expect(app(QuotedAnswerService::class)->ask('Q')->answered)
        ->toBeFalse();
});
