<?php

use App\Ai\Agents\AnswerJudge;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Rag\PolicyRetriever;
use App\Services\Rag\QuotedAnswerService;
use App\Services\Rag\RagAnswer;
use App\Services\Rag\RagEvaluator;
use Illuminate\Database\Eloquent\Collection;

/** A retriever that returns chunks from the given documents. */
function retrieverFinding(array $titles): PolicyRetriever
{
    return new class($titles) extends PolicyRetriever {
        public function __construct(private array $titles) {}

        public function retrieve(string $question, int $limit = 4,
            float $minSimilarity = 0.5): Collection
        {
            return new Collection(array_map(fn ($t) => (new KnowledgeChunk)
                ->setRelation('document',
                    new KnowledgeDocument(['title' => $t])),
                $this->titles));
        }
    };
}

/** An answer service that returns scripted answers in turn. */
function answering(array $answers): QuotedAnswerService
{
    $service = Mockery::mock(QuotedAnswerService::class);
    $service->shouldReceive('ask')->andReturnValues($answers);

    return $service;
}

$cases = [
    ['question' => 'Express cost?', 'source' => 'Shipping',
        'facts' => ['249']],
    ['question' => 'Opening hours?', 'source' => null, 'facts' => []],
];

it('scores retrieval, behaviour, facts and support', function () use ($cases) {
    AnswerJudge::fake([['reason' => 'Stated.', 'supported' => true]]);
    $source = ['number' => 1, 'title' => 'Shipping',
        'content' => 'Express costs INR 249.'];

    $report = (new RagEvaluator(
        retrieverFinding(['Returns', 'Shipping']),
        answering([
            new RagAnswer('Express costs INR 249.', [$source], true, true),
            new RagAnswer('I could not find that.', [], false),
        ]),
    ))->evaluate($cases);

    expect($report['cases'][0])
        ->hit->toBeTrue()
        ->reciprocal_rank->toBe(0.5)
        ->facts_ok->toBeTrue()
        ->supported->toBeTrue()
        ->and($report['cases'][1]['behaved'])->toBeTrue()
        ->and($report['summary'])->toMatchArray([
            'hit_rate' => 1.0, 'mrr' => 0.5,
            'correct_behaviour' => 1.0, 'facts_correct' => 1.0,
            'judged_supported' => 1.0,
        ]);
});

it('counts answering the unanswerable as wrong behaviour', function () use ($cases) {
    AnswerJudge::fake([
        ['reason' => 'Fine.', 'supported' => true],
        ['reason' => 'Not in the sources.', 'supported' => false],
    ]);
    $source = ['number' => 1, 'title' => 'Shipping',
        'content' => 'Ships before 14:00.'];

    $report = (new RagEvaluator(
        retrieverFinding(['Shipping']),
        answering([
            new RagAnswer('Express costs INR 199.', [$source], true, true),
            new RagAnswer('Open 14:00 to 14:00.', [$source], true, true),
        ]),
    ))->evaluate($cases);

    expect($report['cases'][0]['facts_ok'])->toBeFalse()
        ->and($report['cases'][1]['behaved'])->toBeFalse()
        ->and($report['summary']['correct_behaviour'])->toBe(0.5)
        ->and($report['summary']['judged_supported'])->toBe(0.5);
    AnswerJudge::assertPrompted(fn ($prompt) =>
        $prompt->contains('Answer: Open 14:00 to 14:00.'));
});
