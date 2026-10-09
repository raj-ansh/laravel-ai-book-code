<?php

namespace App\Ai\Agents;

use App\Ai\Prompts\PromptLibrary;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.1)]
#[MaxTokens(500)]
class ManagerBriefing implements Agent
{
    use Promptable;

    public function __construct(
        public string $audience = 'a software manager',
        public ?string $version = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return app(PromptLibrary::class)->get(
            'manager-briefing',
            ['audience' => $this->audience],
            $this->version,
        );
    }
}
