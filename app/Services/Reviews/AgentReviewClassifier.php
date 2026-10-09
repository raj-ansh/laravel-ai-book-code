<?php

namespace App\Services\Reviews;

use App\Ai\Agents\ReviewLabeller;
use App\Contracts\ReviewClassifier;
use App\Enums\ReviewTopic;
use App\Models\Review;

/**
 * Uses structured output from any text model. No
 * probabilities, so every value is validated instead.
 */
class AgentReviewClassifier implements ReviewClassifier
{
    public function classify(Review $review): ReviewLabels
    {
        $labels = ReviewLabeller::make()->prompt($review->body);

        $sentiment = filter_var($labels['sentiment'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 5]],
        );

        return new ReviewLabels(
            topic: ReviewTopic::tryFrom(
                (string) ($labels['topic'] ?? '')
            ),
            // Unreadable sentiment: fall back to the star rating.
            sentiment: $sentiment ?: $review->rating,
            // Unreadable: reply, to be safe.
            needsReply: ($labels['needs_reply'] ?? true) !== false,
            classifiedBy: 'agent',
        );
    }
}
