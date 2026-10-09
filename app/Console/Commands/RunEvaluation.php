<?php

namespace App\Console\Commands;

use App\Evaluation\AnalyticsIntentSuite;
use App\Evaluation\EvalRunner;
use App\Evaluation\IntakeCaseSuite;
use App\Evaluation\StoreFactsSuite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:eval {suite} {--model=qwen2.5:1.5b}')]
#[Description('Run an evaluation suite on a local model and store it')]
class RunEvaluation extends Command
{
    private const SUITES = [
        'analytics-intents' => AnalyticsIntentSuite::class,
        'intake-cases' => IntakeCaseSuite::class,
        'store-facts' => StoreFactsSuite::class,
    ];

    public function handle(EvalRunner $runner): int
    {
        $class = self::SUITES[$this->argument('suite')] ?? null;

        if (! $class) {
            $this->error('Suites: '
                .implode(', ', array_keys(self::SUITES)));

            return self::FAILURE;
        }

        $run = $runner->run(app($class), $this->option('model'));

        $this->line("run {$run->id}: {$run->suite} on {$run->model} "
            ."(prompt {$run->prompt_hash})");
        $this->line("passed {$run->passed}/{$run->cases}, score "
            .number_format($run->score, 3).', '
            .round($run->duration_ms / 1000).' s');

        foreach ($run->results->where('passed', false) as $result) {
            $this->warn("  {$result->case_id}: "
                .implode(', ', $result->failed_checks).'  '
                .json_encode($result->output));
        }

        return self::SUCCESS;
    }
}
