<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindCustomerOrders implements Tool
{
    public function __construct(private User $customer) {}

    public function description(): Stringable|string
    {
        return 'List the most recent orders of the customer '
            .'you are talking to, with status and tracking.';
    }

    public function handle(Request $request): Stringable|string
    {
        // The schema is a request to the model, not validation:
        // clamp whatever arrives to a safe range.
        $limit = max(1, min(10, $request->integer('limit', 5)));

        $orders = Order::query()
            ->whereBelongsTo($this->customer)
            ->latest()
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return 'This customer has no orders.';
        }

        return $orders->map(fn (Order $order) => [
            'number' => $order->number,
            'status' => $order->status->value,
            'total' => 'INR '.number_format(
                $order->total_cents / 100, 2
            ),
            'carrier' => $order->carrier,
            'tracking_number' => $order->tracking_number,
            'shipped_at' => $order->shipped_at?->toDateString(),
        ])->toJson();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(10)
                ->description('How many orders to return.'),
        ];
    }
}
