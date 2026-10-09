<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * What to measure, over which period, broken down how: a
 * query described by values from fixed lists. Code turns it
 * into SQL; the model never writes any.
 */
final readonly class QueryIntent
{
    public const METRICS = [
        'order_count', 'revenue', 'average_order_value',
    ];

    public const PERIODS = [
        'last_7_days', 'last_30_days', 'this_month', 'last_month',
    ];

    public const GROUPS = ['status', 'week', 'day'];

    public const STATUSES = [
        'processing', 'shipped', 'delivered', 'cancelled',
    ];

    public function __construct(
        public string $metric,
        public string $period,
        public ?string $groupBy = null,
        public ?string $status = null,
    ) {
        foreach ([
            [$metric, self::METRICS, 'metric'],
            [$period, self::PERIODS, 'period'],
        ] as [$value, $allowed, $name]) {
            if (! in_array($value, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Unsupported {$name} [{$value}]."
                );
            }
        }

        if (! self::listed($groupBy, self::GROUPS)
            || ! self::listed($status, self::STATUSES)) {
            throw new InvalidArgumentException('Unsupported filter.');
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function listed(?string $value, array $allowed): bool
    {
        return $value === null || in_array($value, $allowed, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromModel(array $data): self
    {
        $group = $data['group_by'] ?? 'none';
        $status = $data['status'] ?? 'any';

        return new self(
            (string) ($data['metric'] ?? ''),
            (string) ($data['period'] ?? ''),
            // Grouping by status after filtering to one status
            // gives a single row: drop the grouping.
            $group === 'none' || ($group === 'status' && $status !== 'any')
                ? null : $group,
            $status === 'any' ? null : $status,
        );
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function range(CarbonImmutable $now): array
    {
        return match ($this->period) {
            'last_7_days' => [$now->subDays(7)->startOfDay(), $now],
            'last_30_days' => [$now->subDays(30)->startOfDay(), $now],
            'this_month' => [$now->startOfMonth(), $now],
            'last_month' => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ],
        };
    }

    public function describe(): string
    {
        return str_replace('_', ' ', $this->metric)
            .', '.str_replace('_', ' ', $this->period)
            .($this->groupBy ? ", by {$this->groupBy}" : '')
            .($this->status ? ", {$this->status} orders only" : '');
    }
}
