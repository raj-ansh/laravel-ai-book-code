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
#[MaxTokens(120)]
class TicketSummarizer implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Summarize the support ticket in one sentence '
            .'of at most 25 words. Reply with the summary only.';
    }
}
