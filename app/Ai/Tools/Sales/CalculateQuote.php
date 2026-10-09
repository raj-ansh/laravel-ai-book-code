<?php

namespace App\Ai\Tools\Sales;

use App\Models\User;
use App\Services\Sales\QuoteCalculator;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CalculateQuote implements Tool
{
    use QuoteItems;

    public function __construct(
        private User $customer,
        private QuoteCalculator $calculator,
    ) {}

    public function name(): string
    {
        return 'calculate_quote';
    }

    public function description(): Stringable|string
    {
        return 'Price a list of products for this customer, '
            .'including any discount. Use it for every price or '
            .'total; never calculate amounts yourself. Saves '
            .'nothing.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $quote = $this->calculator->calculate(
                $this->validatedItems($request), $this->customer,
            );
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return json_encode($quote->forModel());
    }
}
