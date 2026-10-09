<?php

use App\Services\Support\FaqMatcher;
use App\Support\Vectors;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

it('measures cosine similarity', function () {
    expect(Vectors::cosine([1, 0], [2, 0]))->toBe(1.0)
        ->and(Vectors::cosine([1, 0], [0, 3]))->toBe(0.0)
        ->and(Vectors::cosine([1, 0], [-1, 0]))->toBe(-1.0);
});

/**
 * Hand-made 3-d "embeddings": [money, delivery, damage].
 */
function fakeMeaning(EmbeddingsPrompt $prompt): array
{
    return array_map(fn (string $text) => match (true) {
        str_contains($text, 'refund'),
        str_contains($text, 'money') => [0.9, 0.1, 0.1],
        str_contains($text, 'delivery'),
        str_contains($text, 'parcel') => [0.1, 0.9, 0.1],
        default => [0.1, 0.1, 0.9],
    }, $prompt->inputs);
}

beforeEach(function () {
    $this->faqs = [
        ['q' => 'When will I get my refund?', 'a' => '7 days.'],
        ['q' => 'How long does delivery take?', 'a' => '3-5 days.'],
    ];
});

it('suggests the FAQ entry closest in meaning', function () {
    Embeddings::fake(fn (EmbeddingsPrompt $p) => fakeMeaning($p));

    $matches = (new FaqMatcher($this->faqs, minScore: 0.6))
        ->match('When does my money come back?');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['q'])->toBe('When will I get my refund?')
        ->and($matches[0]['score'])->toBe(1.0);
});

it('suggests nothing when nothing is close', function () {
    Embeddings::fake(fn (EmbeddingsPrompt $p) => fakeMeaning($p));

    expect((new FaqMatcher($this->faqs, minScore: 0.6))
        ->match('Is the lamp broken?'))->toBe([]);
});

it('embeds the FAQ once and every question each time', function () {
    $batches = 0;
    Embeddings::fake(function (EmbeddingsPrompt $prompt) use (&$batches) {
        $batches++;

        return fakeMeaning($prompt);
    });
    $matcher = new FaqMatcher($this->faqs);

    $matcher->match('Where is my parcel?');
    $matcher->match('Where is my money?');

    // FAQ once (then cached), plus one per question.
    expect($batches)->toBe(3);
});

it('adds the task prefixes a model expects', function () {
    config(['acme.ai.embeddings.prefixes' => [
        'query' => 'search_query: ',
        'document' => 'search_document: ',
    ]]);
    Embeddings::fake(fn (EmbeddingsPrompt $p) => fakeMeaning($p));

    (new FaqMatcher($this->faqs))->match('Where is my parcel?');

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) =>
        $prompt->contains('search_query: Where is my parcel?'));
    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) =>
        $prompt->contains('search_document: When will I get'));
});
