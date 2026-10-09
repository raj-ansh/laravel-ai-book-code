<?php

namespace App\Services\Rag;

use App\Ai\Agents\AnswerJudge;

/**
 * Scores retrieval and answers against questions whose
 * correct source and facts are known.
 */
class RagEvaluator
{
    public function __construct(
        private PolicyRetriever $retriever,
        private QuotedAnswerService $answers,
    ) {}

    /**
     * @param  list<array{question: string, source: ?string,
     *     facts: list<string>}>  $cases
     * @return array{cases: list<array<string, mixed>>,
     *     summary: array<string, float>}
     */
    public function evaluate(array $cases): array
    {
        $results = array_map(fn ($case) => $this->score($case), $cases);

        $answerable = array_values(array_filter(
            $results, fn ($r) => $r['source'] !== null,
        ));
        $answered = array_values(array_filter(
            $results, fn ($r) => $r['answered'],
        ));

        return ['cases' => $results, 'summary' => [
            'hit_rate' => $this->share($answerable, 'hit'),
            'mrr' => $this->mean($answerable, 'reciprocal_rank'),
            'correct_behaviour' => $this->share($results, 'behaved'),
            'facts_correct' => $this->share($answerable, 'facts_ok'),
            'judged_supported' => $this->share($answered, 'supported'),
        ]];
    }

    /**
     * The fraction of rows where $key is true.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function share(array $rows, string $key): float
    {
        $true = array_filter(
            array_column($rows, $key), fn ($v) => $v === true,
        );

        return $rows === [] ? 0.0 : count($true) / count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function mean(array $rows, string $key): float
    {
        return $rows === [] ? 0.0
            : array_sum(array_column($rows, $key)) / count($rows);
    }

    /**
     * @param  array{question: string, source: ?string,
     *     facts: list<string>}  $case
     * @return array<string, mixed>
     */
    private function score(array $case): array
    {
        // 1. Retrieval alone: where does the right document rank?
        $titles = $this->retriever->retrieve($case['question'])
            ->pluck('document.title')->values()->all();
        $rank = $case['source'] === null ? false
            : array_search($case['source'], $titles, true);

        // 2. The whole pipeline.
        $answer = $this->answers->ask($case['question']);
        $factsOk = $answer->answered && collect($case['facts'])
            ->every(fn (string $fact) =>
                preg_match("/{$fact}/i", $answer->text) === 1);

        return [
            'question' => $case['question'],
            'source' => $case['source'],
            'hit' => $rank !== false,
            'reciprocal_rank' => $rank === false ? 0.0 : 1 / ($rank + 1),
            'answered' => $answer->answered,
            // Answer the answerable; refuse the rest.
            'behaved' => $answer->answered === ($case['source'] !== null),
            'facts_ok' => $factsOk,
            'supported' => $answer->answered
                ? $this->judge($answer) : null,
            'answer' => $answer->text,
        ];
    }

    private function judge(RagAnswer $answer): bool
    {
        $sources = collect($answer->sources)
            ->map(fn (array $s) => "[{$s['number']}] {$s['content']}")
            ->implode("\n\n");

        $verdict = AnswerJudge::make()->prompt(
            "Sources:\n\n{$sources}\n\nAnswer: {$answer->text}"
        );

        return ($verdict['supported'] ?? false) === true;
    }
}
