<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(150)]
class QuickAnswer implements Agent, HasProviderOptions
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Answer in one sentence.';
    }

    /**
     * Options only one provider understands.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            // Skip the hidden reasoning of "thinking" models.
            Lab::Ollama => ['think' => false],
            default => [],
        };
    }
}
