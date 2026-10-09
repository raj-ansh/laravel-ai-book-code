<?php

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Embeddings;

class PolicyRetriever
{
    /**
     * The chunks closest in meaning to the question.
     *
     * @return Collection<int, KnowledgeChunk>
     */
    public function retrieve(
        string $question,
        int $limit = 4,
        float $minSimilarity = 0.5,
    ): Collection {
        $prefix = config('acme.ai.embeddings.prefixes.query');

        $vector = Embeddings::for([$prefix.$question])
            ->generate()
            ->first();

        return $this->chunks()
            ->with('document:id,title')
            ->select(['id', 'knowledge_document_id', 'content'])
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->whereVectorSimilarTo('embedding', $vector, $minSimilarity)
            ->limit($limit)
            ->get();
    }

    /**
     * The chunks that may be searched (narrowed in Chapter 35).
     *
     * @return Builder<KnowledgeChunk>
     */
    protected function chunks(): Builder
    {
        return KnowledgeChunk::query();
    }
}
