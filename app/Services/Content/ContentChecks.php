<?php

namespace App\Services\Content;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Brand and fact rules, in code. Each returns plain-language
 * issues for the person approving the draft.
 */
class ContentChecks
{
    /**
     * @return list<string>
     */
    public function issues(
        string $kind,
        string $body,
        Product $product,
    ): array {
        $issues = [];
        $limit = config("acme.ai.brand.limits.{$kind}");

        if (trim($body) === '') {
            return ['The draft is empty.'];
        }

        if ($limit && mb_strlen($body) > $limit) {
            $issues[] = sprintf('%d characters; the limit is %d.',
                mb_strlen($body), $limit);
        }

        foreach (config('acme.ai.brand.banned') as $phrase) {
            if (Str::contains($body, $phrase, ignoreCase: true)) {
                $issues[] = "Uses the banned phrase \"{$phrase}\".";
            }
        }

        if (str_contains($body, '!')) {
            $issues[] = 'Uses exclamation marks.';
        }

        // Any price must be the product's real price, in rupees.
        preg_match_all(
            '/(INR|Rs\.?|\x{20B9}|USD|US\$|\$|EUR|\x{20AC}|GBP|\x{00A3})'
            .'\s?([\d,]+(?:\.\d+)?)/u',
            $body, $prices, PREG_SET_ORDER,
        );
        foreach ($prices as [$all, $currency, $amount]) {
            $cents = (int) round(
                (float) str_replace(',', '', $amount) * 100
            );
            if (! preg_match('/^(INR|Rs\.?|\x{20B9})$/u', $currency)) {
                $issues[] = "Mentions \"{$all}\": prices must be in "
                    .'INR.';
            } elseif ($cents !== $product->price_cents) {
                $issues[] = "Mentions {$all}; the product costs "
                    .Money::inr($product->price_cents).'.';
            }
        }

        if ($kind === 'seo_title' && ! str_ends_with($body, '| Acme')) {
            $issues[] = 'The SEO title must end with "| Acme".';
        }

        return $issues;
    }
}
