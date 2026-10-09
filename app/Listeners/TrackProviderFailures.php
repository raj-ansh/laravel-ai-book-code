<?php

namespace App\Listeners;

use App\Services\Ai\ProviderCircuit;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentFailedOver;

class TrackProviderFailures
{
    public function __construct(private ProviderCircuit $circuit) {}

    /**
     * The SDK moved on to the next provider: count the
     * failure, and say why, so someone can look.
     */
    public function handle(AgentFailedOver $event): void
    {
        $provider = $event->provider->name();
        $this->circuit->recordFailure($provider);

        Log::warning('AI provider failed over', [
            'provider' => $provider,
            'model' => $event->model,
            'agent' => class_basename($event->agent),
            'error' => $event->exception::class,
        ]);
    }
}
