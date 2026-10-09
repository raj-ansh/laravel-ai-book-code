<?php

namespace App\Contracts;

use App\Models\Review;
use App\Services\Reviews\ReviewLabels;

interface ReviewClassifier
{
    public function classify(Review $review): ReviewLabels;
}
