<?php

namespace App\Support;

use InvalidArgumentException;

class Vectors
{
    /**
     * Cosine similarity: 1 same direction, 0 unrelated,
     * -1 opposite.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            throw new InvalidArgumentException(
                'Vectors must have the same, non-zero length.'
            );
        }

        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value * $value;
            $normB += $b[$i] * $b[$i];
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
