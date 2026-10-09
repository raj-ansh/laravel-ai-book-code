<?php

namespace App\Ai\Agents;

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[CacheInstructions]
#[MaxTokens(400)]
class PolicyExpert implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        // A long, stable prefix: worth caching at the provider.
        return "Answer questions using only Acme's policies "
            ."below. If the answer is not there, say so.\n\n"
            .Storage::disk('local')->get('policies/all.md');
    }
}
