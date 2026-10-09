<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\Support\ConversationGuard;
use App\Services\Support\SupportConversation;
use Illuminate\Http\JsonResponse;

class SupportAssistantController extends Controller
{
    public function __invoke(
        SupportChatRequest $request,
        ConversationGuard $guard,
        SupportConversation $conversation,
    ): JsonResponse {
        $guard->ensureOwnedBy(
            $request->input('conversation_id'), $request->user(),
        );

        return response()->json($conversation->reply(
            $request->user(),
            $request->input('conversation_id'),
            $request->string('message'),
        ));
    }
}
