<?php

use App\Enums\ReviewTopic;
use Illuminate\Support\Str;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

it('answers a yes-or-no question about text', function () {
    Classification::fake([
        ['decision' => new BooleanAnswer(0.91)],
    ]);

    expect(Str::decide(
        'I will sue you if this is not fixed.',
        'Does the customer threaten legal action?',
    ))->toBeTrue();
});

it('picks an enum case, or nothing when unsure', function () {
    Classification::fake([
        ['decision' => new ChoiceAnswer('price', [
            'price' => 0.8, 'quality' => 0.2,
        ])],
        ['decision' => new ChoiceAnswer('price', [
            'price' => 0.4, 'quality' => 0.35, 'other' => 0.25,
        ])],
    ]);

    $topics = collect(ReviewTopic::cases());
    $question = 'What is the review mainly about?';

    expect($topics->decide($question, 'Far too expensive.',
        threshold: 0.6))->toBe(ReviewTopic::Price)
        ->and($topics->decide($question, 'Hmm.',
            threshold: 0.6))->toBeNull();
});
