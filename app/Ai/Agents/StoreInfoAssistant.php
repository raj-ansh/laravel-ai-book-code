<?php

namespace App\Ai\Agents;

use App\Services\Ai\ProviderCircuit;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * General store questions: the same answer for everyone,
 * so it can be cached, and it fails over between servers.
 */
#[Temperature(0.0)]
#[MaxTokens(150)]
class StoreInfoAssistant implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            Answer questions about Acme's store in two sentences,
            using only these facts.

            - Delivery takes 3-5 working days in India.
            - Delivery is free for orders over INR 999. Orders
              of INR 999 or less pay INR 79 for delivery.
            - Most items can be returned within 30 days of
              delivery, unused, with the receipt.
            - Electronics (headphones, speakers, chargers,
              lamps) can be returned within 10 days only.
            - Payment: cards, UPI, net banking. No cash on
              delivery above INR 10,000.
            - Support hours: 9:00-21:00 IST, every day.

            Only if none of these facts answers the question,
            reply exactly: "I don't have that information.
            support@acme.test can help."
            TEXT;
    }

    /**
     * Providers in order, without any whose circuit is open.
     *
     * @return array<string, string>
     */
    public function provider(): array
    {
        return app(ProviderCircuit::class)
            ->available(config('acme.ai.failover'));
    }
}
