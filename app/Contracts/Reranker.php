<?php

namespace App\Contracts;

use Closure;
use Illuminate\Support\Collection;

interface Reranker
{
    /**
     * Reorder candidates by relevance to the query and keep
     * the best $limit. $text turns a candidate into text.
     *
     * @template T
     *
     * @param  Collection<int, T>  $candidates
     * @param  Closure(T): string  $text
     * @return Collection<int, T>
     */
    public function rerank(
        string $query,
        Collection $candidates,
        Closure $text,
        int $limit,
    ): Collection;
}
