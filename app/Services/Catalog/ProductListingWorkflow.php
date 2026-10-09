<?php

namespace App\Services\Catalog;

use App\Ai\Agents\CopyReviewer;
use App\Ai\Agents\ProductCopywriter;
use App\Models\Product;
use App\Support\Money;

/**
 * Write, check, review, revise: a workflow whose steps
 * and limits are decided by code, not by a model.
 */
class ProductListingWorkflow
{
    /** One revision fixes most drafts; more rarely helps. */
    private const MAX_REVISIONS = 1;

    public function run(Product $product): ListingDraft
    {
        $facts = $this->facts($product);

        $draft = $this->write($facts);
        $issues = $this->review($draft, $facts);
        $revisions = 0;

        while ($issues && $revisions < self::MAX_REVISIONS) {
            $draft = $this->write($facts, $draft, $issues);
            $issues = $this->review($draft, $facts);
            $revisions++;
        }

        return new ListingDraft($draft, $issues, $revisions);
    }

    private function facts(Product $product): string
    {
        return "Name: {$product->name}\n"
            .'Price: '.Money::inr($product->price_cents)."\n"
            ."Facts: {$product->description}";
    }

    /**
     * @param  list<string>  $issues
     */
    private function write(
        string $facts,
        ?string $draft = null,
        array $issues = [],
    ): string {
        $prompt = "Describe this product.\n\n{$facts}";

        if ($draft !== null) {
            $prompt .= "\n\nYour last draft:\n{$draft}\n\n"
                ."Fix these problems:\n- "
                .implode("\n- ", $issues);
        }

        $reply = ProductCopywriter::make()->prompt($prompt);

        return trim($reply->text);
    }

    /**
     * @return list<string>
     */
    private function review(string $draft, string $facts): array
    {
        // Cheap checks in code first; no model call needed.
        $issues = $this->ruleIssues($draft);

        if ($issues !== []) {
            return $issues;
        }

        $review = CopyReviewer::make()->prompt(
            "Product facts:\n{$facts}\n\nDraft:\n{$draft}"
        );

        if (($review['approved'] ?? null) === true) {
            return [];
        }

        // Rejected, or unreadable: never publish by default.
        $issues = array_values(array_filter(
            (array) ($review['issues'] ?? []),
            fn ($issue) => is_string($issue) && $issue !== '',
        ));

        return $issues
            ?: ['The reviewer gave no usable verdict.'];
    }

    /**
     * @return list<string>
     */
    private function ruleIssues(string $draft): array
    {
        return array_values(array_filter([
            $draft === '' ? 'The draft is empty.' : null,
            str_contains($draft, '!')
                ? 'Remove the exclamation marks.' : null,
            mb_strlen($draft) > 400
                ? 'Shorten it to under 400 characters.' : null,
        ]));
    }
}
