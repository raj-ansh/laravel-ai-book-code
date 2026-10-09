<?php

namespace App\Support;

class RankFusion
{
    /**
     * Reciprocal rank fusion: each list adds 1 / (k + rank)
     * for every id in it. Ids high in several lists win.
     *
     * @param  list<list<int|string>>  $rankings  Ids, best first.
     * @return array<int|string, float>  Fused scores, best first.
     */
    public static function reciprocal(array $rankings, int $k = 60): array
    {
        $scores = [];

        foreach ($rankings as $ranking) {
            foreach (array_values($ranking) as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
            }
        }

        arsort($scores);

        return $scores;
    }
}
