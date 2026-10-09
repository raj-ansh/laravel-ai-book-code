<?php

namespace App\Services\Catalog;

use App\Ai\Agents\SearchQueryParser;
use Illuminate\Support\Str;

final readonly class ParsedQuery
{
    public function __construct(
        public string $terms,
        public ?int $maxPriceCents,
        public bool $inStockOnly,
    ) {}

    /**
     * Ask the model, then keep only what the request supports.
     */
    public static function from(string $request): self
    {
        $request = trim($request);

        // Identifiers are searched as typed: never "corrected".
        if (preg_match('/\bSKU-\d{4}\b/i', $request)) {
            return new self($request, null, false);
        }

        $parsed = SearchQueryParser::make()->prompt($request);

        $terms = trim((string) ($parsed['terms'] ?? ''));
        $price = $parsed['max_price_inr'] ?? null;

        return new self(
            terms: self::sharesAWord($terms, $request)
                ? $terms : $request,
            // A filter counts only if the request mentions it.
            maxPriceCents: is_int($price) && $price > 0
                && preg_match('/\d/', $request) ? $price * 100 : null,
            inStockOnly: ($parsed['in_stock'] ?? false) === true
                && preg_match('/in stock|available/i', $request) === 1,
        );
    }

    private static function sharesAWord(
        string $terms,
        string $request,
    ): bool {
        $words = fn (string $text) => array_filter(
            preg_split('/\W+/', Str::lower($text)),
            fn (string $word) => strlen($word) >= 3,
        );

        return array_intersect($words($terms), $words($request)) !== [];
    }
}
