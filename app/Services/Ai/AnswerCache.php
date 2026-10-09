<?php

namespace App\Services\Ai;

use App\Ai\Agents\StoreInfoAssistant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\FailoverableException;

/**
 * Cached answers to questions whose answer is the same for
 * everyone. Fresh for an hour; kept for a week as a stale
 * copy to serve when every provider is down.
 */
class AnswerCache
{
    private const FRESH = 3600;

    private const STALE = 7 * 24 * 3600;

    /**
     * @return array{text: string, source: string}
     */
    public function answer(string $question): array
    {
        $key = $this->key($question);

        if ($text = Cache::get("{$key}:fresh")) {
            return ['text' => $text, 'source' => 'cache'];
        }

        try {
            $text = StoreInfoAssistant::make()->prompt($question)->text;
        } catch (FailoverableException $e) {
            $stale = Cache::get("{$key}:stale");

            return $stale !== null
                ? ['text' => $stale, 'source' => 'stale']
                : throw $e;
        }

        Cache::put("{$key}:fresh", $text, self::FRESH);
        Cache::put("{$key}:stale", $text, self::STALE);

        return ['text' => $text, 'source' => 'model'];
    }

    /**
     * Same question, same key; changed instructions or model,
     * new key, so old answers are never served for new facts.
     */
    private function key(string $question): string
    {
        $agent = new StoreInfoAssistant;
        $normalised = Str::of($question)->lower()->squish()
            ->rtrim('?!. ');

        return 'answers:'.sha1(implode('|', [
            $agent->instructions(),
            json_encode(config('acme.ai.failover')),
            $normalised,
        ]));
    }
}
