<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Any order placed by anyone in the user's organisation,
 * and nothing outside it.
 */
class OrganisationOrderTool implements Tool
{
    public function __construct(private User $user) {}

    public function name(): string
    {
        return 'get_company_order';
    }

    public function description(): Stringable|string
    {
        return 'Get the status, buyer and total of one order '
            .'placed by the customer\'s company, by its order '
            .'number (for example A-3001).';
    }

    public function handle(Request $request): Stringable|string
    {
        $number = $request->validate([
            'order_number' => ['required', 'regex:/^A-\d{4}$/'],
        ])['order_number'];

        $missing = "No order {$number} was found for your company.";
        $tenant = $this->user->organisation_id;

        // where('organisation_id', null) becomes WHERE ... IS
        // NULL: every customer without a company. Stop first.
        if ($tenant === null) {
            return $missing;
        }

        // The tenant filter is part of the query, never a check
        // made afterwards on a row already loaded.
        $order = Order::query()
            ->where('number', $number)
            ->whereRelation('user', 'organisation_id', $tenant)
            ->with('user:id,name')
            ->first();

        if (! $order) {
            return $missing;
        }

        return json_encode([
            'order_number' => $order->number,
            'status' => $order->status->value,
            'placed_by' => $order->user->name,
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
                ->pattern('^A-\d{4}$')->required(),
        ];
    }
}
