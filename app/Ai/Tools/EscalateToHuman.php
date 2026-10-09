<?php

namespace App\Ai\Tools;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Hands the conversation to a person by opening a ticket,
 * which Chapter 13's triage then routes.
 */
class EscalateToHuman implements Tool
{
    public function __construct(private User $customer) {}

    public function name(): string
    {
        return 'escalate_to_human';
    }

    public function description(): Stringable|string
    {
        return 'Pass the conversation to a member of staff. Use it '
            .'when the customer asks for a person, is upset, or '
            .'needs something the other tools cannot do.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'max:2000'],
        ]);

        // One escalation is enough: a repeat within ten minutes
        // returns the ticket already open.
        $ticket = Ticket::where('user_id', $this->customer->id)
            ->where('subject', 'like', 'Escalated from chat:%')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first()
            ?? Ticket::create([
                'user_id' => $this->customer->id,
                'subject' => 'Escalated from chat: '
                    .Str::limit($validated['reason'], 80),
                'body' => $validated['summary'],
            ]);

        return "Ticket #{$ticket->id} is open. A member of staff "
            .'will reply by email within one working day.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()
                ->description('Why a person is needed, briefly.')
                ->required(),
            'summary' => $schema->string()
                ->description('What the customer wants, and what '
                    .'has been tried.')
                ->required(),
        ];
    }
}
