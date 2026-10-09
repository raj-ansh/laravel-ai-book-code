<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\FileSearch;
use Stringable;

#[MaxTokens(400)]
class HandbookSearchAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Answer questions about Acme\'s policies. Search '
            .'the handbook first and answer only from what you '
            .'find. If the handbook does not say, say so.';
    }

    /**
     * The store lives at the same provider as the model.
     */
    public function provider(): ?string
    {
        return config('acme.ai.handbook_store.provider');
    }

    public function tools(): iterable
    {
        return [
            new FileSearch(
                [config('acme.ai.handbook_store.id')],
                where: ['audience' => 'customers'],
            ),
        ];
    }
}
