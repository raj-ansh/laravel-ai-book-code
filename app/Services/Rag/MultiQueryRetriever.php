<?php

namespace App\Services\Rag;

use App\Ai\Agents\QuestionRewriter;
use App\Models\KnowledgeChunk;
use App\Support\RankFusion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rewrites the question (using the conversation so far),
 * retrieves for several phrasings, and fuses the results.
 */
class MultiQueryRetriever
{
    public function __construct(private PolicyRetriever $retriever) {}

    /**
     * @return array{question: string,
     *     chunks: Collection<int, KnowledgeChunk>}
     */
    public function retrieve(
        string $question,
        ?string $previous = null,
        int $limit = 4,
    ): array {
        $prompt = $previous === null
            ? "Latest question: {$question}"
            : "Earlier question: {$previous}\n"
                ."Latest question: {$question}";

        $rewritten = QuestionRewriter::make()->prompt($prompt);

        $standalone = trim((string) ($rewritten['standalone'] ?? ''))
            ?: $question;

        $variants = (array) ($rewritten['variants'] ?? []);

        // The customer's own words always take part.
        $queries = collect([$question, $standalone])
            ->merge(array_slice($variants, 0, 2))
            ->filter(fn ($q) => is_string($q) && trim($q) !== '')
            ->unique()
            ->values();

        $byId = [];
        $rankings = $queries->map(function (string $query) use (&$byId) {
            $chunks = $this->retriever->retrieve($query, limit: 6);
            $chunks->each(function (KnowledgeChunk $chunk) use (&$byId) {
                $byId[$chunk->id] = $chunk;
            });

            return $chunks->pluck('id')->all();
        })->all();

        $ids = array_slice(
            array_keys(RankFusion::reciprocal($rankings)), 0, $limit,
        );

        return [
            'question' => $standalone,
            'chunks' => new Collection(
                array_map(fn ($id) => $byId[$id], $ids)
            ),
        ];
    }
}
