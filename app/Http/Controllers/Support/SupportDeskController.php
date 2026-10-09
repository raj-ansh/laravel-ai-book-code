<?php

namespace App\Http\Controllers\Support;

use App\Ai\Agents\SupportDeskAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\Support\ConversationGuard;
use Illuminate\Http\JsonResponse;

class SupportDeskController extends Controller
{
    public function __invoke(
        SupportChatRequest $request,
        ConversationGuard $guard,
    ): JsonResponse {
        $user = $request->user();
        $conversationId = $request->input('conversation_id');

        $guard->ensureOwnedBy($conversationId, $user);

        $response = SupportDeskAgent::make(customer: $user)
            ->continueOrStart($conversationId, as: $user)
            ->prompt($request->string('message'));

        // A paused run has no text yet: the refund is waiting.
        $waiting = $response->hasPendingApprovals();

        return response()->json([
            'conversation_id' => $response->conversationId,
            'reply' => $waiting && $response->text === ''
                ? 'Your request is waiting for a member of staff.'
                : $response->text,
            'awaiting_approval' => $waiting,
        ]);
    }
}
