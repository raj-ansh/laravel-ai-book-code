<?php

namespace App\Services\Catalog;

final readonly class ListingDraft
{
    /**
     * @param  list<string>  $issues  Problems left unresolved.
     */
    public function __construct(
        public string $text,
        public array $issues,
        public int $revisions,
    ) {}

    public function approved(): bool
    {
        return $this->issues === [];
    }
}
