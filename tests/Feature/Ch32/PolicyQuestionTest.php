<?php

use App\Ai\Agents\PolicyAnswerer;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Rag\PolicyQuestionService;
use App\Services\Rag\PolicyRetriever;
use Illuminate\Database\Eloquent\Collection;

/** A retriever that returns the given chunks, no database. */
function retrieving(array $chunks): void
{
    $found = new Collection(array_map(function (array $chunk) {
        return (new KnowledgeChunk(['content' => $chunk[1]]))
            ->setRelation('document',
                new KnowledgeDocument(['title' => $chunk[0]]));
    }, $chunks));

    app()->instance(PolicyRetriever::class, new class($found)
        extends PolicyRetriever {
        public function __construct(private Collection $found) {}

        public function retrieve(string $question, int $limit = 4,
            float $minSimilarity = 0.5): Collection
        {
            return $this->found;
        }
    });
}

beforeEach(function () {
    retrieving([
        ['Shipping', 'Express delivery costs INR 249, metros only.'],
        ['Returns', 'Returns are accepted within 30 days.'],
    ]);
});

it('answers from the sources it cites', function () {
    PolicyAnswerer::fake(['Express costs INR 249 in metros [1].']);

    $answer = app(PolicyQuestionService::class)
        ->ask('How much is express delivery?');

    expect($answer->answered)->toBeTrue()
        ->and($answer->cited)->toBeTrue()
        ->and(array_column($answer->sources, 'title'))
        ->toBe(['Shipping']);
    PolicyAnswerer::assertPrompted(fn ($prompt) =>
        $prompt->contains("[1] Shipping\nExpress delivery costs")
        && $prompt->contains('Question: How much is express'));
});

it('does not ask the model when nothing is retrieved', function () {
    retrieving([]);
    PolicyAnswerer::fake();

    $answer = app(PolicyQuestionService::class)
        ->ask('What are your opening hours?');

    expect($answer->text)->toBe(PolicyQuestionService::NOT_FOUND);
    PolicyAnswerer::assertNeverPrompted();
});

it('rejects citations of sources that do not exist', function () {
    PolicyAnswerer::fake(['Express is free [3].']);

    expect(app(PolicyQuestionService::class)->ask('Express?')
        ->answered)->toBeFalse();
});

it('accepts an uncited answer only if its numbers are sourced', function () {
    PolicyAnswerer::fake([
        'Express delivery costs INR 249.',
        'Express delivery costs INR 199.',
    ]);
    $service = app(PolicyQuestionService::class);

    $sourced = $service->ask('Express?');
    $invented = $service->ask('Express?');

    expect($sourced->answered)->toBeTrue()
        ->and($sourced->cited)->toBeFalse()
        ->and($sourced->sources)->toHaveCount(2)
        ->and($invented->answered)->toBeFalse();
});

it('passes the model\'s refusal through', function () {
    PolicyAnswerer::fake([PolicyQuestionService::NOT_FOUND]);

    expect(app(PolicyQuestionService::class)->ask('Dubai?')
        ->answered)->toBeFalse();
});
