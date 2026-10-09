<?php

namespace App\Services\Text;

use App\Ai\Agents\DigestWriter;
use App\Ai\Agents\SectionSummarizer;
use Laravel\Ai\Responses\Data\FinishReason;

/**
 * Map-reduce summarisation: notes on each chunk (map),
 * then one summary of the notes (reduce).
 */
class LongDocumentSummarizer
{
    public function __construct(private TextChunker $chunker) {}

    public function summarize(string $text): Summary
    {
        $chunks = $this->chunker->split($text);
        $count = count($chunks);

        // Map: notes on each chunk. One chunk needs no notes.
        $notes = $chunks;

        if ($count > 1) {
            foreach ($chunks as $i => $chunk) {
                $notes[$i] = SectionSummarizer::make()->prompt(
                    'Part '.($i + 1)." of {$count}:\n\n{$chunk}"
                )->text;
            }
        }

        // Reduce: one summary from all the notes.
        $response = DigestWriter::make()->prompt(
            implode("\n\n", $notes)
        );

        $summary = trim($response->text);

        return new Summary(
            text: $summary,
            sections: $count,
            truncated: $response->steps->last()?->finishReason
                === FinishReason::Length,
            unsupportedNumbers: array_values(array_diff(
                $this->numbers($summary),
                $this->numbers($text),
            )),
        );
    }

    /**
     * The numbers in a text: "18,420" and "18420" are equal.
     *
     * @return list<string>
     */
    private function numbers(string $text): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $number) => rtrim(
                str_replace(',', '', $number), '.'
            ),
            $matches[0],
        )));
    }
}
