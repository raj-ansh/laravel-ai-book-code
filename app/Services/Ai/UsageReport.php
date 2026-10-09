<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Usage and cost since a date, grouped for a dashboard:
 * by organisation, by feature and by model.
 */
class UsageReport
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function since(CarbonImmutable $from): array
    {
        $by = fn (string $column) => AiUsage::query()
            ->leftJoin('organisations',
                'organisations.id', '=', 'ai_usage.organisation_id')
            ->where('ai_usage.created_at', '>=', $from)
            ->groupBy($column)
            ->orderByDesc('tokens')
            ->get([
                DB::raw("{$column} as name"),
                DB::raw('count(*) as calls'),
                DB::raw('sum(input_tokens + output_tokens) as tokens'),
                DB::raw('sum(cost_micros) as cost_micros'),
            ])
            ->map(fn ($row) => [
                'name' => $row->name ?? 'consumers',
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
                'cost_usd' => round($row->cost_micros / 1_000_000, 4),
            ])->all();

        return [
            'organisations' => $by('organisations.name'),
            'features' => $by('ai_usage.feature'),
            'models' => $by('ai_usage.model'),
        ];
    }
}
