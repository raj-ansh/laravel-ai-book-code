<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Look up any order by number. Staff only.')]
#[IsReadOnly]
class LookupOrder extends Tool
{
    /**
     * Only staff see this tool in the server's tool list.
     */
    public function shouldRegister(Request $request): bool
    {
        return (bool) $request->user()?->is_staff;
    }

    public function handle(Request $request): Response
    {
        // Checked again here: listing is not authorisation.
        if (! $request->user()?->is_staff) {
            return Response::error('Only staff can look up orders.');
        }

        $number = $request->validate([
            'number' => ['required', 'string', 'regex:/^A-\d{4}$/'],
        ])['number'];

        $order = Order::with('user')->firstWhere('number', $number);

        if (! $order) {
            return Response::text("No order {$number}.");
        }

        return Response::text(
            "Order {$order->number} for {$order->user->name}: "
            .$order->status->value.', '
            .Money::inr($order->total_cents).'.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->string()
                ->pattern('^A-\d{4}$')
                ->required(),
        ];
    }
}
