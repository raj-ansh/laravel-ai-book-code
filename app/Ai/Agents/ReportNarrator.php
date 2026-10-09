<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.2)]
#[MaxTokens(200)]
class ReportNarrator implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Answer the manager\'s question in two sentences '
            .'from the results given. Quote figures exactly as '
            .'they appear in the results. Do not calculate new '
            .'figures, compare with anything not given, or '
            .'speculate about causes.';
    }
}
