<?php

namespace App\Services\Text;

/**
 * Splits text into chunks of at most $maxChars, keeping
 * paragraphs, and failing that sentences, whole.
 */
class TextChunker
{
    public function __construct(private int $maxChars = 6000) {}

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->pieces($text) as $piece) {
            $candidate = $current === ''
                ? $piece : "{$current}\n\n{$piece}";

            if (mb_strlen($candidate) <= $this->maxChars) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            $current = $piece;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Paragraphs, with over-long ones cut into sentences.
     *
     * @return list<string>
     */
    private function pieces(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $pieces = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if (mb_strlen($paragraph) <= $this->maxChars) {
                $pieces[] = $paragraph;

                continue;
            }

            foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) as $s) {
                $pieces[] = $s;
            }
        }

        return array_values(array_filter($pieces));
    }
}
