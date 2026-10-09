<?php

namespace App\Services\Rag;

final readonly class RagAnswer
{
    /**
     * @param  list<array{number: int, title: string,
     *     content: string}>  $sources  What the answer rests on.
     * @param  bool  $cited  Whether the model cited its sources.
     */
    public function __construct(
        public string $text,
        public array $sources,
        public bool $answered,
        public bool $cited = false,
    ) {}
}
