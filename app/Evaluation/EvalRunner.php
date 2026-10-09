<?php

namespace App\Evaluation;

use App\Models\EvalRun;
use Laravel\Ai\Exceptions\FailoverableException;

/**
 * Runs every case of a suite on one model and stores the
 * results, so runs can be compared later.
 */
class EvalRunner
{
    public function run(EvalSuite $suite, string $model): EvalRun
    {
        $start = hrtime(true);
        $results = [];

        foreach ($suite->cases() as $case) {
            try {
                ['output' => $output, 'checks' => $checks]
                    = $suite->run($case, $model);
            } catch (FailoverableException $e) {
                // The provider failed, not the model: record it
                // as a failed case, visibly.
                [$output, $checks] = [null, ['provider' => false]];
            }

            $failed = array_keys(
                array_filter($checks, fn (bool $ok) => ! $ok),
            );
            $results[] = [
                'case_id' => $case['id'],
                'passed' => $failed === [],
                'score' => $checks === [] ? 0
                    : round(1 - count($failed) / count($checks), 3),
                'output' => $output,
                'failed_checks' => $failed,
            ];
        }

        $results = collect($results);
        $run = EvalRun::create([
            'suite' => $suite->name(),
            'model' => $model,
            'prompt_hash' => substr(sha1($suite->prompt()), 0, 12),
            'cases' => $results->count(),
            'passed' => $results->where('passed', true)->count(),
            'score' => round($results->avg('score'), 3),
            'duration_ms' => intdiv(hrtime(true) - $start, 1_000_000),
        ]);
        $run->results()->createMany($results->all());

        return $run;
    }
}
