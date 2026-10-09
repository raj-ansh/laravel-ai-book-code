<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.8)]
#[MaxTokens(200)]
class ProductCopywriter implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You write product descriptions for Acme, an '
            .'online store. Write two sentences, plain and '
            .'specific, with no exclamation marks.';
    }

    /**
     * The provider for this feature, from configuration.
     */
    public function provider(): ?string
    {
        return config('acme.ai.copywriter.provider');
    }

    /**
     * The model for this feature, from configuration.
     */
    public function model(): ?string
    {
        return config('acme.ai.copywriter.model');
    }
}
