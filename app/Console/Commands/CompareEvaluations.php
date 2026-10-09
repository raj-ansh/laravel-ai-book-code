<?php

namespace App\Console\Commands;

use App\Models\EvalRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:eval-compare {before} {after}')]
#[Description('Compare two evaluation runs case by case')]
class CompareEvaluations extends Command
{
    public function handle(): int
    {
        $before = EvalRun::findOrFail($this->argument('before'));
        $after = EvalRun::findOrFail($this->argument('after'));

        if ($before->suite !== $after->suite) {
            $this->error('Runs are from different suites.');

            return self::FAILURE;
        }

        $was = $before->results->pluck('passed', 'case_id');
        $now = $after->results->pluck('passed', 'case_id');

        $this->line("{$before->model} ({$before->prompt_hash}) "
            ."{$before->passed}/{$before->cases} -> "
            ."{$after->model} ({$after->prompt_hash}) "
            ."{$after->passed}/{$after->cases}");

        foreach ($now as $case => $passed) {
            match (true) {
                ! $was->get($case) && $passed
                    => $this->info("  fixed      {$case}"),
                $was->get($case) && ! $passed
                    => $this->error("  regressed  {$case}"),
                default => null,
            };
        }

        return self::SUCCESS;
    }
}
