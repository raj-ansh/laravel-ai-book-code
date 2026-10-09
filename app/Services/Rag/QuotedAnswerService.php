<?php

namespace App\Services\Rag;

use App\Ai\Agents\QuotingAnswerer;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Str;

/**
 * Answers only when every quote the model gives can be
 * found, word for word, in the source it names.
 */
class QuotedAnswerService
{
    public function __construct(private MultiQueryRetriever $retriever) {}

    public function ask(
        string $question,
        ?string $previous = null,
    ): RagAnswer {
        ['question' => $standalone, 'chunks' => $chunks] =
            $this->retriever->retrieve($question, $previous);

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

        $context = collect($sources)->map(fn (array $s) =>
            "[{$s['number']}] {$s['title']}\n{$s['content']}"
        )->implode("\n\n");

        $result = QuotingAnswerer::make()->prompt(
            "Sources:\n\n{$context}\n\nQuestion: {$standalone}"
        );

        $quotes = (array) ($result['quotes'] ?? []);
        $answer = trim((string) ($result['answer'] ?? ''));

        $cited = $this->sourcesOf($quotes, $sources);

        if ($answer === '' || $cited === null) {
            return $this->notFound();
        }

        return new RagAnswer($answer, array_values(array_filter(
            $sources,
            fn (array $source) => in_array($source['number'], $cited),
        )), answered: true, cited: true);
    }

    /**
     * The sources that really contain the quotes, or null if
     * any quote is not in the sources word for word. The
     * text is the evidence; the model's numbering is not.
     *
     * @param  list<array{source?: int, text?: string}>  $quotes
     * @param  list<array{number: int, content: string}>  $sources
     * @return list<int>|null
     */
    private function sourcesOf(array $quotes, array $sources): ?array
    {
        $found = [];

        foreach ($quotes as $quote) {
            $text = Str::squish((string) ($quote['text'] ?? ''));
            $match = collect($sources)->first(
                fn (array $source) => mb_strlen($text) >= 10
                    && str_contains(
                        Str::squish($source['content']), $text,
                    ),
            );

            if ($match === null) {
                return null;
            }

            $found[] = $match['number'];
        }

        return $found === [] ? null : array_values(array_unique($found));
    }

    private function notFound(): RagAnswer
    {
        return new RagAnswer(
            PolicyQuestionService::NOT_FOUND, [], answered: false,
        );
    }
}
