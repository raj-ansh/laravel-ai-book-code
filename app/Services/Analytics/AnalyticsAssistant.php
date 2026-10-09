<?php

namespace App\Services\Analytics;

use App\Ai\Agents\AnalyticsQuestion;
use App\Ai\Agents\ReportNarrator;
use InvalidArgumentException;

/**
 * Question in, checked figures out: the model chooses what
 * to measure and puts the result into words; code measures.
 */
class AnalyticsAssistant
{
    /** Requests to change data: refused before any model call. */
    private const WRITES = '/\b(delete|remove|drop|update|change|'
        .'edit|insert|refund|truncate|set)\b/i';

    /** Subjects the metrics cannot answer. */
    private const OUT_OF_SCOPE = '/\b(customers?|who|products?|'
        .'items?|skus?|stock|categor(y|ies))\b/i';

    public const REFUSAL = 'I can report order counts, revenue and '
        .'average order value by period, status, week or day. I '
        .'cannot answer that question, or change any data.';

    public function __construct(private AnalyticsRunner $runner) {}

    /**
     * @return array{intent: ?QueryIntent, rows: list<array{label:
     *     string, value: string}>, summary: string}
     */
    public function ask(string $question): array
    {
        if (preg_match(self::WRITES, $question)
            || preg_match(self::OUT_OF_SCOPE, $question)) {
            return $this->refuse();
        }

        try {
            $intent = QueryIntent::fromModel(
                AnalyticsQuestion::make()->prompt($question)->toArray()
            );
        } catch (InvalidArgumentException) {
            return $this->refuse();
        }

        $rows = $this->runner->run($intent);
        $table = collect($rows)
            ->map(fn (array $row) => "{$row['label']}: {$row['value']}")
            ->implode("\n");

        $summary = trim(ReportNarrator::make()->prompt(
            "Question: {$question}\nQuery: {$intent->describe()}\n"
            ."Results:\n{$table}"
        )->text);

        // Every figure in the summary must come from the results
        // (or the question: "the last 7 days").
        $known = $question."\n".$table;

        return ['intent' => $intent, 'rows' => $rows,
            'summary' => $this->figuresMatch($summary, $known)
                ? $summary
                : 'See the figures below.'];
    }

    /**
     * @return array{intent: null, rows: array{}, summary: string}
     */
    private function refuse(): array
    {
        return ['intent' => null, 'rows' => [],
            'summary' => self::REFUSAL];
    }

    private function figuresMatch(string $summary, string $known): bool
    {
        $figures = fn (string $text) => preg_match_all(
            '/\d[\d,]*(?:\.\d+)?/', $text, $m) ? $m[0] : [];
        $allowed = array_map(
            fn ($f) => str_replace(',', '', $f), $figures($known),
        );

        foreach ($figures($summary) as $figure) {
            $figure = str_replace(',', '', $figure);

            if (! in_array($figure, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
