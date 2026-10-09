<?php

namespace App\Services\Rag;

use App\Ai\Agents\PolicyAnswerer;
use App\Models\KnowledgeChunk;

/**
 * Retrieval-augmented generation: retrieve, answer from
 * what was retrieved, then check the answer against it.
 */
class PolicyQuestionService
{
    public const NOT_FOUND = 'I could not find that in our policies.';

    public function __construct(private PolicyRetriever $retriever) {}

    public function ask(string $question): RagAnswer
    {
        $chunks = $this->retriever->retrieve($question);

        // Nothing relevant: say so without asking a model.
        if ($chunks->isEmpty()) {
            return $this->notFound();
        }

        $sources = $chunks->values()->map(
            fn (KnowledgeChunk $chunk, int $i) => [
                'number' => $i + 1,
                'title' => $chunk->document->title,
                'content' => $chunk->content,
            ],
        )->all();

        $text = trim(PolicyAnswerer::make()->prompt(
            "Sources:\n\n{$this->context($sources)}\n\n"
            ."Question: {$question}"
        )->text);

        if ($text === '' || str_contains($text, self::NOT_FOUND)) {
            return $this->notFound();
        }

        $cited = $this->citedNumbers($text);

        // Citing a source that does not exist: not grounded.
        if (array_diff($cited, array_column($sources, 'number'))) {
            return $this->notFound();
        }

        if ($cited !== []) {
            return new RagAnswer($text, array_values(array_filter(
                $sources,
                fn (array $source) => in_array($source['number'], $cited),
            )), answered: true, cited: true);
        }

        // No citations: accept only if every number is sourced.
        return $this->numbersAreSourced($text, $sources)
            ? new RagAnswer($text, $sources, answered: true)
            : $this->notFound();
    }

    /**
     * @param  list<array{number: int, title: string,
     *     content: string}>  $sources
     */
    private function context(array $sources): string
    {
        return collect($sources)->map(fn (array $source) =>
            "[{$source['number']}] {$source['title']}\n"
            .$source['content']
        )->implode("\n\n");
    }

    /**
     * @return list<int>
     */
    private function citedNumbers(string $text): array
    {
        preg_match_all('/\[(\d+)\]/', $text, $matches);

        return array_values(array_unique(
            array_map('intval', $matches[1])
        ));
    }

    /**
     * @param  list<array{content: string}>  $sources
     */
    private function numbersAreSourced(string $text, array $sources): bool
    {
        $numbers = fn (string $s) => preg_match_all(
            '/\d[\d,]*/', str_replace(',', '', $s), $m,
        ) ? $m[0] : [];

        return array_diff(
            $numbers($text),
            $numbers(implode(' ', array_column($sources, 'content'))),
        ) === [];
    }

    private function notFound(): RagAnswer
    {
        return new RagAnswer(self::NOT_FOUND, [], answered: false);
    }
}
