<?php

namespace App\Services\Knowledge;

/**
 * What the knowledge search actually found during one
 * answer. Citations come from here, never from the model.
 */
final class CitationLedger
{
    /**
     * @var list<array{answer: string,
     *     sources: list<array<string, mixed>>}>
     */
    private array $hits = [];

    /** @var list<string> What the model searched for. */
    private array $queries = [];

    public function searchedFor(string $query): void
    {
        $this->queries[] = $query;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function found(string $answer, array $sources): void
    {
        $this->hits[] = ['answer' => $answer, 'sources' => $sources];
    }

    /**
     * @return list<string>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    public function hasAnswer(): bool
    {
        return $this->hits !== [];
    }

    /**
     * Every verified source, once.
     *
     * @return list<array{title: string, quote: string}>
     */
    public function citations(): array
    {
        return collect($this->hits)
            ->flatMap(fn (array $hit) => $hit['sources'])
            ->map(fn (array $s) => [
                'title' => $s['title'],
                'quote' => str($s['content'])->squish()
                    ->limit(160)->toString(),
            ])
            ->unique('title')->values()->all();
    }
}
