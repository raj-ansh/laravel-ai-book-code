<?php

namespace App\Services\Text;

final readonly class Summary
{
    /**
     * @param  list<string>  $unsupportedNumbers  Not in the source.
     */
    public function __construct(
        public string $text,
        public int $sections,
        public bool $truncated,
        public array $unsupportedNumbers = [],
    ) {}

    /**
     * Safe to show without a person checking it first.
     */
    public function passesChecks(): bool
    {
        return ! $this->truncated && $this->unsupportedNumbers === [];
    }
}
