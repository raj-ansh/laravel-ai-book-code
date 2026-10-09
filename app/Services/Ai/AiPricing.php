<?php

namespace App\Services\Ai;

/**
 * Turns token counts into money, from the price table in
 * config/acme.php ("provider/model", then "provider").
 */
class AiPricing
{
    /**
     * The cost in millionths of a US dollar.
     */
    public function costMicros(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
    ): int {
        $prices = config('acme.ai.pricing');
        $price = $prices["{$provider}/{$model}"]
            ?? $prices[$provider] ?? ['input' => 0, 'output' => 0];

        // Prices are USD per million tokens, so tokens x price
        // is already in millionths of a dollar.
        return (int) round($inputTokens * $price['input']
            + $outputTokens * $price['output']);
    }
}
