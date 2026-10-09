<?php

namespace App\Console\Commands;

use App\Services\Ai\CapacityPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:capacity {--per-minute=60} {--hours=24}')]
#[Description('Workers and tokens per minute needed per agent')]
class PlanAiCapacity extends Command
{
    public function handle(CapacityPlanner $planner): int
    {
        $rate = (float) $this->option('per-minute');

        $this->line("At {$rate} requests per minute per agent, from "
            .'the last '.$this->option('hours').' hours of traces:');
        $this->table(
            ['Agent', 'Runs', 'Mean s', 'In flight', 'Workers',
                'Tokens/run', 'Tokens/min'],
            array_map(fn (array $row) => [
                $row['agent'], $row['runs'], $row['mean_s'],
                $row['in_flight'], $row['workers'],
                number_format($row['tokens_per_run']),
                number_format($row['tokens_per_minute']),
            ], $planner->plan($rate, (int) $this->option('hours'))),
        );

        return self::SUCCESS;
    }
}
