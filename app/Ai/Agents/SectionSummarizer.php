<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
#[Temperature(0.1)]
#[MaxTokens(300)]
class SectionSummarizer implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You take notes on one part of a longer document.
            Write at most five short bullet points. Keep every
            number, date, name and decision exactly as written.
            Do not add anything that is not in the text.
            TEXT;
    }
}
