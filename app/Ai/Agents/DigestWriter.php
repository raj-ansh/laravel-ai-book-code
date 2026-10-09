<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxTokens(400)]
class DigestWriter implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You write a summary for a busy manager. Write one
            paragraph of at most five sentences, then up to three
            bullet points headed "Actions:". Use only the facts
            given. Keep the numbers exact.
            TEXT;
    }
}
