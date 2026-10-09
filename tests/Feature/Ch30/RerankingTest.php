<?php

use App\Ai\Agents\RelevanceJudge;
use App\Contracts\Reranker;
use App\Services\Search\JudgeReranker;
use App\Services\Search\ProviderReranker;
use Laravel\Ai\Prompts\RerankingPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

beforeEach(function () {
    $this->candidates = collect([
        ['name' => 'Walnut bookshelf'],
        ['name' => 'Solar garden lights'],
        ['name' => 'Kids night light'],
    ]);
    $this->text = fn (array $item) => $item['name'];
});

it('reranks with a reranking provider', function () {
    Reranking::fake([[
        new RankedDocument(1, 'Solar garden lights', 0.92),
        new RankedDocument(2, 'Kids night light', 0.41),
    ]]);

    $ranked = (new ProviderReranker)->rerank(
        'balcony lighting', $this->candidates, $this->text, 2,
    );

    expect($ranked->pluck('name')->all())
        ->toBe(['Solar garden lights', 'Kids night light']);
    Reranking::assertReranked(fn (RerankingPrompt $prompt) =>
        $prompt->query === 'balcony lighting'
        && $prompt->limit === 2);
});

it('reranks with a judge model and drops poor matches', function () {
    RelevanceJudge::fake([['scores' => [
        ['index' => 0, 'score' => 1],
        ['index' => 1, 'score' => 9],
        ['index' => 2, 'score' => 6],
    ]]]);

    $ranked = (new JudgeReranker)->rerank(
        'balcony lighting', $this->candidates, $this->text, 5,
    );

    expect($ranked->pluck('name')->all())
        ->toBe(['Solar garden lights', 'Kids night light']);
    RelevanceJudge::assertPrompted(fn ($prompt) =>
        $prompt->contains('1. Solar garden lights'));
});

it('keeps unscored candidates, after scored ones', function () {
    RelevanceJudge::fake([['scores' => [
        ['index' => 2, 'score' => 8],
        ['index' => 7, 'score' => 10], // no such candidate
    ]]]);

    $ranked = (new JudgeReranker)->rerank(
        'night light', $this->candidates, $this->text, 5,
    );

    expect($ranked->pluck('name')->all())->toBe([
        'Kids night light', 'Walnut bookshelf', 'Solar garden lights',
    ]);
});

it('picks the reranker from configuration', function () {
    config(['acme.ai.reranker' => 'provider']);

    expect(app(Reranker::class))
        ->toBeInstanceOf(ProviderReranker::class);
});
