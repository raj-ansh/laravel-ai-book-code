<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Runs a QueryIntent with the query builder and returns rows
 * of [label, value] ready to show and to narrate.
 */
class AnalyticsRunner
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public function run(
        QueryIntent $intent,
        ?CarbonImmutable $now = null,
    ): array {
        [$from, $to] = $intent->range($now ?? CarbonImmutable::now());

        $orders = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($intent->status, fn ($q, $status) => $q
                ->where('status', $status))
            ->get(['status', 'total_cents', 'created_at']);

        $groups = match ($intent->groupBy) {
            'status' => $orders->groupBy(
                fn (Order $o) => $o->status->value),
            'week' => $orders->groupBy(fn (Order $o) => 'week of '
                .$o->created_at->startOfWeek()->toDateString()),
            'day' => $orders->groupBy(fn (Order $o) => $o->created_at
                ->toDateString()),
            default => collect(['all orders' => $orders]),
        };

        return $groups->sortKeys()->map(fn (Collection $g, $label) => [
            'label' => (string) $label,
            'value' => $this->measure($intent->metric, $g),
        ])->values()->all();
    }

    private function measure(string $metric, Collection $orders): string
    {
        return match ($metric) {
            'order_count' => (string) $orders->count(),
            'revenue' => Money::inr($orders->sum('total_cents')),
            'average_order_value' => $orders->isEmpty() ? 'n/a'
                : Money::inr((int) round($orders->avg('total_cents'))),
        };
    }
}
