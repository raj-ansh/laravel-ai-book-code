<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.2)]
#[MaxTokens(300)]
class Explainer implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You explain technical ideas to PHP '
            .'developers in plain English. Answer in '
            .'at most three sentences.';
    }
}
