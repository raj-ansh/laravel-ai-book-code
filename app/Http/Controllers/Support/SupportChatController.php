<?php

namespace App\Http\Controllers\Support;

use App\Ai\Agents\SupportChatAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\Support\ConversationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Contracts\ConversationStore;

class SupportChatController extends Controller
{
    public function __construct(private ConversationGuard $guard) {}

    /**
     * Send a message, starting or continuing a conversation.
     */
    public function store(SupportChatRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversationId = $request->input('conversation_id');

        $this->guard->ensureOwnedBy($conversationId, $user);

        $response = SupportChatAgent::make(customer: $user)
            ->continueOrStart($conversationId, as: $user)
            ->prompt($request->string('message'));

        return response()->json([
            'conversation_id' => $response->conversationId,
            'reply' => $response->text,
        ]);
    }

    /**
     * Show a conversation's messages, oldest first.
     */
    public function show(
        Request $request,
        string $conversation,
        ConversationStore $store,
    ): JsonResponse {
        $this->guard->ensureOwnedBy($conversation, $request->user());

        $messages = $store->paginateConversationMessages(
            $conversation, perPage: 50,
        );

        return response()->json([
            // The store pages newest first; show oldest first.
            'messages' => collect($messages->items())
                ->reverse()
                ->values()
                ->map(fn ($message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                ]),
        ]);
    }
}
