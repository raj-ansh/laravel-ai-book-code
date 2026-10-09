<?php

namespace App\Console\Commands;

use App\Contracts\ReviewClassifier;
use App\Models\Review;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('reviews:classify')]
#[Description('Label unclassified reviews by topic and sentiment')]
class ClassifyReviews extends Command
{
    public function handle(ReviewClassifier $classifier): int
    {
        $rows = Review::whereNull('classified_by')->get()->map(
            function (Review $review) use ($classifier) {
                $classifier->classify($review)->applyTo($review);

                return [
                    $review->rating,
                    Str::limit($review->body, 40),
                    $review->topic?->value ?? '(unsure)',
                    $review->sentiment,
                    $review->needs_reply ? 'yes' : 'no',
                ];
            },
        );

        $this->table(
            ['Stars', 'Review', 'Topic', 'Sentiment', 'Reply'],
            $rows,
        );

        return self::SUCCESS;
    }
}
