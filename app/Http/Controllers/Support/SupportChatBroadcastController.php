<?php

namespace App\Http\Controllers\Support;

use App\Ai\Agents\SupportChatAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\Support\ConversationGuard;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\JsonResponse;

class SupportChatBroadcastController extends Controller
{
    public function __invoke(
        SupportChatRequest $request,
        ConversationGuard $guard,
    ): JsonResponse {
        $user = $request->user();
        $conversationId = $request->input('conversation_id');

        $guard->ensureOwnedBy($conversationId, $user);

        // A queue worker runs the agent and broadcasts each
        // event to the user's private channel as it arrives.
        SupportChatAgent::make(customer: $user)
            ->continueOrStart($conversationId, as: $user)
            ->broadcastOnQueue(
                $request->string('message'),
                new PrivateChannel("support.{$user->id}"),
            );

        return response()->json(['status' => 'queued'], 202);
    }
}
