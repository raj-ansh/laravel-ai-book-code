<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

// Provider tools run on the provider's servers, so the agent
// is pinned to a provider that offers both of them.
#[Provider(Lab::Anthropic)]
#[MaxTokens(800)]
class LaravelDocsResearcher implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You answer questions about Laravel using the '
            .'official documentation at laravel.com. Search, read '
            .'the relevant page, and cite it.';
    }

    /**
     * @return list<ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            (new WebSearch)->max(3)->allow(['laravel.com']),
            (new WebFetch)->max(2)->allow(['laravel.com']),
        ];
    }
}
