<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
#[Temperature(0.0)]
#[MaxTokens(5)]
class SentimentTagger implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            Classify the sentiment of a customer message.
            Reply with one word: positive, negative or neutral.

            Examples:

            Message: The lamp arrived early and looks great.
            Sentiment: positive

            Message: Third time I am asking. Where is my refund?
            Sentiment: negative

            Message: Can I change the delivery address?
            Sentiment: neutral

            Message: Great, another broken charger. Just great.
            Sentiment: negative
            TEXT;
    }
}
