<?php

namespace App\Services\Search;

use App\Contracts\Reranker;
use Closure;
use Illuminate\Support\Collection;

/**
 * A dedicated reranking model (Cohere, Jina, Voyage)
 * through the SDK's Reranking API.
 */
class ProviderReranker implements Reranker
{
    public function rerank(
        string $query,
        Collection $candidates,
        Closure $text,
        int $limit,
    ): Collection {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        return $candidates->rerank($text, $query, limit: $limit);
    }
}
