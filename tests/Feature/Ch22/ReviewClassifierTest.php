<?php

use App\Ai\Agents\ReviewLabeller;
use App\Contracts\ReviewClassifier;
use App\Enums\ReviewTopic;
use App\Models\Review;
use App\Services\Reviews\AgentReviewClassifier;
use App\Services\Reviews\ProbabilisticReviewClassifier;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Ai\Responses\Data\ScoreAnswer;

beforeEach(function () {
    $this->review = Review::factory()->create([
        'rating' => 2,
        'body' => 'Arrived ten days late, box crushed.',
    ]);
});

/** A scripted Classification API answer. */
function answers(float $delivery, float $reply): array
{
    return [
        'topic' => new ChoiceAnswer('delivery', [
            'delivery' => $delivery, 'quality' => 1 - $delivery,
        ]),
        'sentiment' => new ScoreAnswer(1.2, [
            0 => 0.1, 1 => 0.7, 2 => 0.2, 3 => 0.0, 4 => 0.0,
        ], ['very negative', 'negative', 'mixed',
            'positive', 'very positive']),
        'needs_reply' => new BooleanAnswer($reply),
    ];
}

it('labels a review from probabilities', function () {
    Classification::fake([answers(delivery: 0.85, reply: 0.45)]);

    $labels = (new ProbabilisticReviewClassifier)
        ->classify($this->review);

    expect($labels->topic)->toBe(ReviewTopic::Delivery)
        ->and($labels->sentiment)->toBe(2)
        ->and($labels->needsReply)->toBeTrue();
    Classification::assertClassified(
        fn (ClassificationPrompt $prompt) =>
            $prompt->state === 'Arrived ten days late, box crushed.'
    );
});

it('leaves an uncertain topic for a person', function () {
    Classification::fake([answers(delivery: 0.55, reply: 0.2)]);

    $labels = (new ProbabilisticReviewClassifier)
        ->classify($this->review);

    expect($labels->topic)->toBeNull()
        ->and($labels->needsReply)->toBeFalse();
});

it('labels a review with structured output', function () {
    ReviewLabeller::fake([[
        'topic' => 'delivery', 'sentiment' => 2,
        'needs_reply' => true,
    ]]);

    $labels = (new AgentReviewClassifier)->classify($this->review);

    expect($labels->topic)->toBe(ReviewTopic::Delivery)
        ->and($labels->sentiment)->toBe(2)
        ->and($labels->classifiedBy)->toBe('agent');
});

it('falls back safely on invalid structured output', function () {
    ReviewLabeller::fake([[
        'topic' => 'shipping', 'sentiment' => 9,
    ]]);

    $labels = (new AgentReviewClassifier)->classify($this->review);

    expect($labels->topic)->toBeNull()
        ->and($labels->sentiment)->toBe(2) // the star rating
        ->and($labels->needsReply)->toBeTrue();
});

it('picks the classifier from configuration', function () {
    config(['acme.ai.review_classifier' => 'api']);

    expect(app(ReviewClassifier::class))
        ->toBeInstanceOf(ProbabilisticReviewClassifier::class);
});

it('classifies stored reviews from the command', function () {
    ReviewLabeller::fake([[
        'topic' => 'delivery', 'sentiment' => 2,
        'needs_reply' => true,
    ]]);

    $this->artisan('reviews:classify')->assertSuccessful();

    expect($this->review->fresh())
        ->topic->toBe(ReviewTopic::Delivery)
        ->classified_by->toBe('agent');
});
