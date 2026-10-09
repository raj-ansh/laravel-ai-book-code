<?php

namespace App\Services\Ai;

use Laravel\Ai\Ai;
use Laravel\Ai\Providers\Provider;

/**
 * Builds a provider from an API key a customer supplied
 * ("bring your own key"), without touching config/ai.php.
 */
class CustomerKeyProvider
{
    public function openAi(
        string $key,
        ?string $model = null,
    ): Provider {
        return Ai::build(array_filter([
            'driver' => 'openai',
            'key' => $key,
            'models' => $model
                ? ['text' => ['default' => $model]]
                : null,
        ]));
    }
}
