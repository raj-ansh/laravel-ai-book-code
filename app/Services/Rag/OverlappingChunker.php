<?php

namespace App\Services\Rag;

/**
 * Packs sentences into chunks of about $maxChars, repeating
 * the last $overlap sentences at the start of the next chunk
 * so that no fact is cut off from its context.
 */
class OverlappingChunker
{
    public function __construct(
        private int $maxChars = 800,
        private int $overlap = 1,
    ) {}

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        // Sentence ends, but not "4." in "4. Invoices"; and
        // paragraph breaks.
        $sentences = preg_split(
            '/(?<=[.!?])(?<!\b\d\.)(?<!\b\d\d\.)\s+|\n{2,}/',
            trim($text),
            flags: PREG_SPLIT_NO_EMPTY,
        );

        $chunks = [];
        $current = [];

        foreach ($sentences as $sentence) {
            $candidate = [...$current, trim($sentence)];

            $tooLong = mb_strlen(implode(' ', $candidate))
                > $this->maxChars;

            if ($current !== [] && $tooLong) {
                $chunks[] = implode(' ', $current);
                $current = [
                    ...array_slice($current, -$this->overlap),
                    trim($sentence),
                ];

                continue;
            }

            $current = $candidate;
        }

        if ($current !== []) {
            $chunks[] = implode(' ', $current);
        }

        return $chunks;
    }
}
