<?php

namespace App\Ai\Tools\Sales;

use App\Models\Quotation;
use App\Models\User;
use App\Services\Sales\QuoteCalculator;
use App\Support\Money;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateQuotation implements Tool
{
    use QuoteItems;

    public function __construct(
        private User $customer,
        private QuoteCalculator $calculator,
    ) {}

    public function name(): string
    {
        return 'create_quotation';
    }

    public function description(): Stringable|string
    {
        return 'Save a formal quotation, valid for 14 days, and '
            .'return its reference. Only use it when the customer '
            .'explicitly asks for a quotation.';
    }

    public function handle(Request $request): Stringable|string
    {
        // A retried tool call returns the quotation it already
        // created instead of saving a second one.
        if ($existing = Quotation::firstWhere(
            'tool_call_id', $request->toolCallId(),
        )) {
            return "Quotation {$existing->reference} already exists.";
        }

        try {
            // Prices come from the database, never from the model.
            $quote = $this->calculator->calculate(
                $this->validatedItems($request), $this->customer,
            );
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        $quotation = Quotation::create([
            'user_id' => $this->customer->id,
            'reference' => 'Q-'.Str::upper(Str::random(8)),
            'lines' => $quote->lines,
            'subtotal_cents' => $quote->subtotalCents,
            'discount_cents' => $quote->discountCents,
            'total_cents' => $quote->totalCents,
            'valid_until' => now()->addDays(14),
            'tool_call_id' => $request->toolCallId(),
        ]);

        return "Saved quotation {$quotation->reference} for "
            .Money::inr($quotation->total_cents).', valid until '
            .$quotation->valid_until->toDateString().'.';
    }
}
