<?php

namespace App\Services\Reviews;

use App\Contracts\ReviewClassifier;
use App\Enums\ReviewTopic;
use App\Models\Review;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Classification\Score;

/**
 * Uses the SDK's Classification API, which returns
 * probabilities, from a classification provider.
 */
class ProbabilisticReviewClassifier implements ReviewClassifier
{
    /** Below this, the topic is left for a person. */
    private const TOPIC_THRESHOLD = 0.6;

    /** Missing a reply costs more than an extra one. */
    private const REPLY_THRESHOLD = 0.4;

    public function classify(Review $review): ReviewLabels
    {
        $topics = collect(ReviewTopic::cases())->mapWithKeys(
            fn (ReviewTopic $topic) => [
                $topic->value => $topic->description(),
            ],
        )->all();

        $answers = Classification::of($review->body)
            ->question('topic', new Choice(
                'What is the review mainly about?', $topics,
            ))
            ->question('sentiment', new Score(
                'How does the customer feel?',
                ['very negative', 'negative', 'mixed',
                    'positive', 'very positive'],
            ))
            ->question('needs_reply', new Boolean(
                'Should Acme reply to this review?',
                ['true' => 'A complaint, a question or a request.',
                    'false' => 'Praise or a neutral remark.'],
            ))
            ->classify();

        $topic = $answers['topic'];

        return new ReviewLabels(
            topic: $topic->probabilityOf($topic->choice)
                >= self::TOPIC_THRESHOLD
                ? ReviewTopic::from($topic->choice) : null,
            sentiment: $answers['sentiment']->level() + 1,
            needsReply: $answers['needs_reply']
                ->isTrue(self::REPLY_THRESHOLD),
            classifiedBy: 'classification-api',
        );
    }
}
