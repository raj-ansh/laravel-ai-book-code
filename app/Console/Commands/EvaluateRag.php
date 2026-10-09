<?php

namespace App\Console\Commands;

use App\Services\Rag\RagEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('rag:eval {--cases=resources/eval/policy-questions.json}
    {--answers : Print every answer after the table}')]
#[Description('Score RAG retrieval and answers on a golden set')]
class EvaluateRag extends Command
{
    public function handle(RagEvaluator $evaluator): int
    {
        $report = $evaluator->evaluate(json_decode(
            file_get_contents(base_path($this->option('cases'))), true,
        ));

        $mark = fn (?bool $value) => match ($value) {
            true => 'yes', false => 'NO', null => '-',
        };

        $this->table(
            ['Question', 'Hit', 'Behaved', 'Facts', 'Judge'],
            array_map(fn (array $case) => [
                Str::limit($case['question'], 34),
                $case['source'] === null ? '-' : $mark($case['hit']),
                $mark($case['behaved']),
                $case['source'] === null ? '-' : $mark($case['facts_ok']),
                $mark($case['supported']),
            ], $report['cases']),
        );

        if ($this->option('answers')) {
            foreach ($report['cases'] as $case) {
                $this->line("Q: {$case['question']}");
                $this->line("A: {$case['answer']}");
            }
        }

        foreach ($report['summary'] as $metric => $value) {
            $this->line(sprintf('%-18s %.2f', $metric, $value));
        }

        return self::SUCCESS;
    }
}
