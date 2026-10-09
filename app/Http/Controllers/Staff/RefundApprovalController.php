<?php

namespace App\Http\Controllers\Staff;

use App\Ai\Agents\SupportDeskAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;

class RefundApprovalController extends Controller
{
    /**
     * The tool calls in a conversation that wait for a person.
     */
    public function index(
        string $conversation,
        ConversationStore $store,
    ): JsonResponse {
        Gate::authorize('approve-refunds');

        return response()->json([
            'pending' => array_map(
                fn ($approval) => $approval->toArray(),
                $store->pendingApprovalsFor($conversation),
            ),
        ]);
    }

    /**
     * Approve or reject them, and let the agent continue.
     */
    public function decide(
        Request $request,
        string $conversation,
    ): JsonResponse {
        Gate::authorize('approve-refunds');

        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.id' => ['required', 'string'],
            'decisions.*.approve' => ['required', 'boolean'],
            'decisions.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        $decisions = [];

        foreach ($validated['decisions'] as $decision) {
            $decisions[$decision['id']] = $decision['approve']
                ? Decision::approve()
                : Decision::reject($decision['note'] ?? null);

            Log::info('Tool call decision', [
                'conversation_id' => $conversation,
                'tool_call_id' => $decision['id'],
                'approved' => $decision['approve'],
                'staff_id' => $request->user()->id,
            ]);
        }

        // Resume as the customer: their tools, their scope.
        $customer = Conversation::findOrFail($conversation)
            ->participant;

        $response = SupportDeskAgent::make(customer: $customer)
            ->continue($conversation, as: $customer)
            ->prompt(Decisions::from($decisions));

        return response()->json(['reply' => $response->text]);
    }
}
