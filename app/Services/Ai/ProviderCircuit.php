<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * A circuit breaker per provider: after repeated failures,
 * stop sending it requests for a while instead of waiting
 * for each one to fail.
 */
class ProviderCircuit
{
    public function recordFailure(string $provider): void
    {
        $config = config('acme.ai.circuit');
        $key = "circuit:{$provider}:failures";

        Cache::add($key, 0, $config['window']);

        if (Cache::increment($key) >= $config['failures']) {
            Cache::put("circuit:{$provider}:open", true,
                $config['cooldown']);
            Cache::forget($key);
        }
    }

    public function isOpen(string $provider): bool
    {
        return Cache::has("circuit:{$provider}:open");
    }

    /**
     * The providers to try, without those whose circuit is
     * open. If every circuit is open, try the last anyway:
     * a slow answer beats no answer.
     *
     * @param  array<string, string>  $chain
     * @return array<string, string>
     */
    public function available(array $chain): array
    {
        $closed = array_filter($chain,
            fn (string $provider) => ! $this->isOpen($provider),
            ARRAY_FILTER_USE_KEY);

        return $closed ?: array_slice($chain, -1, 1, true);
    }
}
