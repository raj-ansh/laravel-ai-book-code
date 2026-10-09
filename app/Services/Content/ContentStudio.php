<?php

namespace App\Services\Content;

use App\Ai\Agents\ContentWriter;
use App\Models\ContentDraft;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Drafts marketing content for a product, checks it, and
 * publishes what a person approves.
 */
class ContentStudio
{
    public function __construct(private ContentChecks $checks) {}

    /**
     * One model call, four drafts, each with its issues.
     *
     * @return Collection<int, ContentDraft>
     */
    public function draft(
        Product $product,
        User $requestedBy,
    ): Collection {
        $written = ContentWriter::make()->prompt(
            "Product: {$product->name}\n"
            .'Price: '.Money::inr($product->price_cents)."\n"
            ."Facts: {$product->description}"
        );

        return collect(array_keys(ContentDraft::COLUMNS))->map(
            function (string $kind) use ($written, $product, $requestedBy) {
                $body = trim((string) ($written[$kind] ?? ''));

                return ContentDraft::create([
                    'product_id' => $product->id,
                    'kind' => $kind,
                    'body' => $body,
                    'issues' => $this->checks->issues(
                        $kind, $body, $product,
                    ),
                    'status' => 'draft',
                    'requested_by' => $requestedBy->id,
                ]);
            },
        );
    }

    /**
     * Publish a draft to the product, replacing the earlier
     * approved draft of the same kind.
     */
    public function approve(ContentDraft $draft, User $by): void
    {
        if ($draft->status !== 'draft') {
            throw new InvalidArgumentException(
                "This draft is already {$draft->status}."
            );
        }

        DB::transaction(function () use ($draft, $by) {
            ContentDraft::where('product_id', $draft->product_id)
                ->where('kind', $draft->kind)
                ->where('status', 'approved')
                ->update(['status' => 'replaced']);

            $draft->update([
                'status' => 'approved', 'decided_by' => $by->id,
            ]);

            if ($column = ContentDraft::COLUMNS[$draft->kind]) {
                $draft->product->update([$column => $draft->body]);
            }
        });
    }

    public function reject(ContentDraft $draft, User $by): void
    {
        $draft->update(['status' => 'rejected', 'decided_by' => $by->id]);
    }
}
