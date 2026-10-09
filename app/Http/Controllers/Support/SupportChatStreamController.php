<?php

namespace App\Http\Controllers\Support;

use App\Ai\Agents\SupportChatAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\Support\ConversationGuard;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\Response;

class SupportChatStreamController extends Controller
{
    public function __invoke(
        SupportChatRequest $request,
        ConversationGuard $guard,
    ): Response {
        $user = $request->user();
        $conversationId = $request->input('conversation_id');

        $guard->ensureOwnedBy($conversationId, $user);

        $stream = SupportChatAgent::make(customer: $user)
            ->continueOrStart($conversationId, as: $user)
            ->stream($request->string('message'))
            ->then(function (StreamedAgentResponse $response) {
                Log::info('Support chat streamed', [
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                ]);
            });

        // The id exists before the first token, so the client
        // can read it from a header and send it next time.
        $response = $stream->toResponse($request);

        $response->headers->set(
            'X-Conversation-Id', $stream->conversationId,
        );

        return $response;
    }
}
