<?php

namespace App\Services\Search;

use App\Ai\Agents\RelevanceJudge;
use App\Contracts\Reranker;
use Closure;
use Illuminate\Support\Collection;

/**
 * Reranks with a general chat model scoring each candidate:
 * works with any provider, including local models.
 */
class JudgeReranker implements Reranker
{
    /** Candidates scored below this are dropped. */
    private const MIN_SCORE = 4;

    public function rerank(
        string $query,
        Collection $candidates,
        Closure $text,
        int $limit,
    ): Collection {
        $candidates = $candidates->values();

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $list = $candidates->map(
            fn ($candidate, int $i) => "{$i}. ".$text($candidate),
        )->implode("\n");

        $response = RelevanceJudge::make()->prompt(
            "Query: {$query}\n\nResults:\n{$list}"
        );

        // Keep only scores for candidates that exist.
        $scores = [];

        foreach ($response['scores'] ?? [] as $row) {
            $index = $row['index'] ?? null;

            if (is_int($index) && $candidates->has($index)) {
                $scores[$index] = (int) ($row['score'] ?? 0);
            }
        }

        // Unscored candidates keep their original order, last.
        return $candidates
            ->map(fn ($candidate, int $i) => [
                'item' => $candidate,
                'score' => $scores[$i] ?? -1,
                'rank' => $i,
            ])
            ->reject(fn ($c) => $c['score'] >= 0
                && $c['score'] < self::MIN_SCORE)
            ->sortBy([['score', 'desc'], ['rank', 'asc']])
            ->take($limit)
            ->pluck('item')
            ->values();
    }
}
