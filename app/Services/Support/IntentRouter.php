<?php

namespace App\Services\Support;

use App\Support\Vectors;
use Laravel\Ai\Embeddings;

/**
 * Recognises what a support message is about by comparing it
 * with example messages (Chapter 27), so the critical routes
 * do not depend on a model choosing the right tool.
 */
class IntentRouter
{
    public const EXAMPLES = [
        'policy' => [
            'Can I return an item after opening it?',
            'How long does delivery take?',
            'What does the warranty cover?',
            'Is standard shipping free?',
            'How long until I get my refund?',
            'Do you deliver outside India?',
            'Can I return a gift card?',
        ],
        'order' => [
            'Where is my order?',
            'Has my parcel been shipped yet?',
            'What is the tracking number for my order?',
        ],
        'refund' => [
            'Please refund my order, it arrived broken.',
            'I want my money back for this order.',
        ],
    ];

    public function __construct(private float $minScore = 0.6) {}

    /**
     * The best-matching intent, or null when nothing is close.
     */
    public function route(string $message): ?string
    {
        $examples = collect(self::EXAMPLES)->flatMap(
            fn (array $texts, string $intent) => array_map(
                fn (string $text) => [$intent, $text], $texts,
            ),
        )->values();

        // Example vectors never change: embed them once.
        $vectors = Embeddings::for($examples->pluck(1)->all())
            ->cache()->generate()->embeddings;
        $message = Embeddings::for([$message])->generate()->first();

        $best = $examples->keys()
            ->map(fn (int $i) => [
                $examples[$i][0],
                Vectors::cosine($message, $vectors[$i]),
            ])
            ->sortByDesc(1)
            ->first();

        return $best[1] >= $this->minScore ? $best[0] : null;
    }
}
