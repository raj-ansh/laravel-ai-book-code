<?php

namespace App\Console\Commands;

use App\Services\Ai\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:usage {--days=30}')]
#[Description('Show AI usage and cost by organisation, feature, model')]
class ShowAiUsage extends Command
{
    public function handle(UsageReport $report): int
    {
        $from = CarbonImmutable::now()
            ->subDays((int) $this->option('days'))->startOfDay();

        foreach ($report->since($from) as $group => $rows) {
            $this->table(
                [ucfirst($group), 'Calls', 'Tokens', 'Cost (USD)'],
                array_map(fn (array $row) => [
                    $row['name'], $row['calls'],
                    number_format($row['tokens']),
                    number_format($row['cost_usd'], 4),
                ], $rows),
            );
        }

        return self::SUCCESS;
    }
}
