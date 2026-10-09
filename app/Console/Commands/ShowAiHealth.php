<?php

namespace App\Console\Commands;

use App\Services\Ai\AiHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:health {--minutes=60}')]
#[Description('Calls, error rate and latency per agent and tool')]
class ShowAiHealth extends Command
{
    public function handle(AiHealth $health): int
    {
        $this->table(
            ['Agent / tool', 'Calls', 'Errors', 'p50 ms', 'p95 ms'],
            array_map(fn (array $row) => [
                $row['name'], $row['calls'],
                ($row['error_rate'] * 100).'%',
                number_format($row['p50_ms']),
                number_format($row['p95_ms']),
            ], $health->lastMinutes((int) $this->option('minutes'))),
        );

        return self::SUCCESS;
    }
}
