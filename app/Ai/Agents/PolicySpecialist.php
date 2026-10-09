<?php

namespace App\Ai\Agents;

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.0)]
#[MaxTokens(300)]
class PolicySpecialist implements Agent, CanActAsTool
{
    use Promptable;

    public function name(): string
    {
        return 'ask_policy_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Ask about Acme\'s policies: returns, refunds, '
            .'shipping and warranty. Write a complete question.';
    }

    public function instructions(): Stringable|string
    {
        $disk = Storage::disk('kb');

        // The handbook lives only in this agent's context.
        $policies = collect($disk->files('policies'))
            ->map(fn (string $path) => $disk->get($path))
            ->implode("\n\n");

        return "Answer questions using only these policies. If "
            ."the answer is not there, say so.\n\n{$policies}";
    }
}
