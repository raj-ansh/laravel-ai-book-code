<?php

namespace App\Services\Ai;

use App\Models\AiSpan;
use Illuminate\Support\Collection;

/**
 * The numbers to watch: per agent and per tool, how many
 * calls, how many failed, and how slow the slow ones were.
 */
class AiHealth
{
    /**
     * @return list<array<string, mixed>>
     */
    public function lastMinutes(int $minutes): array
    {
        return AiSpan::query()
            ->whereIn('kind', ['agent', 'tool'])
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->get(['kind', 'name', 'status', 'duration_ms'])
            ->groupBy(fn (AiSpan $s) => "{$s->kind}:{$s->name}")
            ->map(fn (Collection $spans, string $key) => [
                'name' => $key,
                'calls' => $spans->count(),
                'error_rate' => round($spans
                    ->where('status', 'error')->count()
                    / $spans->count(), 3),
                'p50_ms' => $this->percentile($spans, 0.50),
                'p95_ms' => $this->percentile($spans, 0.95),
            ])
            ->sortByDesc('calls')->values()->all();
    }

    /** Nearest-rank percentile of the durations. */
    private function percentile(Collection $spans, float $p): float
    {
        $sorted = $spans->pluck('duration_ms')->sort()->values();

        return $sorted[(int) ceil($p * $sorted->count()) - 1];
    }
}
