<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RefundOrder implements Approvable, Tool
{
    use InteractsWithApprovals;

    /** Refunds up to this amount need no human approval. */
    public const AUTO_APPROVE_CENTS = 100_000; // INR 1,000

    public function __construct(private User $customer) {}

    public function name(): string
    {
        return 'refund_order';
    }

    public function description(): Stringable|string
    {
        return 'Refund one of the customer\'s orders in full, by '
            .'order number. Only use it when the customer asks for '
            .'a refund and gives a reason.';
    }

    /**
     * Decide, per call, whether a person must approve it first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $order = $this->ownOrder($request['order_number'] ?? '');

        if ($order && $order->total_cents <= self::AUTO_APPROVE_CENTS) {
            return false;
        }

        return Approval::required(
            'A refund over '.Money::inr(self::AUTO_APPROVE_CENTS)
            .' moves money and needs a member of staff.'
        );
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'order_number' => [
                'required', 'string', 'regex:/^A-\d{4}$/',
            ],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $order = $this->ownOrder($validated['order_number']);

        if (! $order) {
            return "No order {$validated['order_number']} was "
                .'found for this customer.';
        }

        // Idempotent: one refund per order, however often asked.
        $refund = Refund::firstOrCreate(
            ['order_id' => $order->id],
            [
                'amount_cents' => $order->total_cents,
                'reason' => $validated['reason'],
                'tool_call_id' => $request->toolCallId(),
            ],
        );

        return $refund->wasRecentlyCreated
            ? 'Refunded '.Money::inr($refund->amount_cents)
                ." for order {$order->number}. It reaches the "
                .'original payment method within 7 working days.'
            : "Order {$order->number} was already refunded.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()
                ->pattern('^A-\d{4}$')->required(),
            'reason' => $schema->string()
                ->description('The customer\'s reason, briefly.')
                ->required(),
        ];
    }

    private function ownOrder(string $number): ?Order
    {
        $order = Order::firstWhere('number', $number);

        return $order && Gate::forUser($this->customer)
            ->allows('view', $order) ? $order : null;
    }
}
