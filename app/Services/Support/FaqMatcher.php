<?php

namespace App\Services\Support;

use App\Support\Vectors;
use Laravel\Ai\Embeddings;

/**
 * Suggests FAQ answers for a question by meaning, not
 * keywords. Fine for a few hundred entries in memory.
 */
class FaqMatcher
{
    /**
     * @param  list<array{q: string, a: string}>  $faqs
     */
    public function __construct(
        private array $faqs,
        private float $minScore = 0.6,
    ) {}

    public static function fromFile(string $path): self
    {
        return new self(
            json_decode(file_get_contents($path), true),
            (float) config('acme.ai.embeddings.faq_min_score', 0.6),
        );
    }

    /**
     * @return list<array{q: string, a: string, score: float}>
     */
    public function match(string $question, int $limit = 3): array
    {
        $prefixes = config('acme.ai.embeddings.prefixes');

        // FAQ vectors change only when the FAQ does: cache them.
        $documents = Embeddings::for(array_map(
            fn (array $faq) => $prefixes['document'].$faq['q'],
            $this->faqs,
        ))->cache()->generate()->embeddings;

        $query = Embeddings::for([$prefixes['query'].$question])
            ->generate()
            ->first();

        return collect($this->faqs)
            ->map(fn (array $faq, int $i) => $faq + [
                'score' => round(
                    Vectors::cosine($query, $documents[$i]), 3,
                ),
            ])
            ->where('score', '>=', $this->minScore)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }
}
