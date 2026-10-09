<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider([Lab::Anthropic, Lab::OpenAI])]
#[Temperature(0.2)]
#[MaxTokens(300)]
class FaqAnswerer implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You answer general questions about shopping '
            .'at Acme, an online store. If you are not sure, '
            .'say so and suggest contacting support.';
    }
}
