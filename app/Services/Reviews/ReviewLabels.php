<?php

namespace App\Services\Reviews;

use App\Enums\ReviewTopic;
use App\Models\Review;

final readonly class ReviewLabels
{
    /**
     * @param  ReviewTopic|null  $topic  Null when unsure.
     * @param  int  $sentiment  1 (very negative) to 5.
     */
    public function __construct(
        public ?ReviewTopic $topic,
        public int $sentiment,
        public bool $needsReply,
        public string $classifiedBy,
    ) {}

    public function applyTo(Review $review): Review
    {
        $review->update([
            'topic' => $this->topic,
            'sentiment' => $this->sentiment,
            'needs_reply' => $this->needsReply,
            'classified_by' => $this->classifiedBy,
        ]);

        return $review;
    }
}
