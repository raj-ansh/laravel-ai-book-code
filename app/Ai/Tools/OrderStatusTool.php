<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class OrderStatusTool implements Tool
{
    public function __construct(private User $customer) {}

    /**
     * The name the model sees. Defaults to the class name.
     */
    public function name(): string
    {
        return 'get_order_status';
    }

    public function description(): Stringable|string
    {
        return 'Get the status, carrier, tracking number and '
            .'ship date of one order, by its order number '
            .'(for example A-1001). Use it whenever the customer '
            .'asks about a specific order.';
    }

    public function handle(Request $request): Stringable|string
    {
        // A failed rule is sent back to the model as text,
        // so it can correct the argument and call again.
        $validated = $request->validate([
            'order_number' => [
                'required', 'string', 'regex:/^A-\d{4}$/',
            ],
        ]);

        $number = $validated['order_number'];
        $order = Order::firstWhere('number', $number);

        // Same answer for "not found" and "not yours": never
        // reveal that someone else's order exists.
        if (! $order || Gate::forUser($this->customer)
            ->denies('view', $order)) {
            return "No order {$number} was found for this customer.";
        }

        return json_encode([
            'order_number' => $order->number,
            'status' => $order->status->value,
            'carrier' => $order->carrier ?? 'not shipped yet',
            'tracking_number' => $order->tracking_number
                ?? 'not shipped yet',
            'shipped_on' => $order->shipped_at?->toDateString()
                ?? 'not shipped yet',
            'total' => 'INR '.number_format(
                $order->total_cents / 100, 2
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()
                ->pattern('^A-\d{4}$')
                ->description('The order number, like A-1001.')
                ->required(),
        ];
    }
}
