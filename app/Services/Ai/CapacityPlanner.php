<?php

namespace App\Services\Ai;

use App\Models\AiSpan;
use App\Models\AiUsage;

/**
 * Little's law on measured data: requests in flight =
 * arrival rate x time per request. From that, how many
 * workers an agent needs, and how many tokens per minute
 * the provider must allow, at a target request rate.
 */
class CapacityPlanner
{
    /** Spare capacity for bursts and slow days. */
    private const HEADROOM = 1.5;

    /**
     * @return list<array<string, mixed>>
     */
    public function plan(float $perMinute, int $hours): array
    {
        $since = now()->subHours($hours);

        $tokens = AiUsage::where('created_at', '>=', $since)
            ->get(['feature', 'invocation_id', 'input_tokens',
                'output_tokens'])
            ->groupBy('feature')
            ->map(fn ($rows) => $rows->groupBy('invocation_id')
                ->map(fn ($calls) => $calls->sum('input_tokens')
                    + $calls->sum('output_tokens'))
                ->avg());

        return AiSpan::where('kind', 'agent')
            ->where('created_at', '>=', $since)
            ->get(['name', 'duration_ms'])
            ->groupBy('name')
            ->map(function ($spans, string $agent) use (
                $perMinute, $tokens,
            ) {
                $seconds = $spans->avg('duration_ms') / 1000;
                $inFlight = $perMinute / 60 * $seconds;
                $perRun = (int) round($tokens[$agent] ?? 0);

                return [
                    'agent' => $agent,
                    'runs' => $spans->count(),
                    'mean_s' => round($seconds, 1),
                    'in_flight' => round($inFlight, 1),
                    'workers' => (int) ceil($inFlight * self::HEADROOM),
                    'tokens_per_run' => $perRun,
                    'tokens_per_minute' => (int) round(
                        $perRun * $perMinute),
                ];
            })
            ->sortByDesc('workers')->values()->all();
    }
}
