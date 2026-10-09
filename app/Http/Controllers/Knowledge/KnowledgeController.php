<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Jobs\AnswerKnowledgeQuestion;
use App\Models\KnowledgeAnswer;
use App\Services\Knowledge\KnowledgePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class KnowledgeController extends Controller
{
    /**
     * Answer now, in the request.
     */
    public function ask(
        Request $request,
        KnowledgePlatform $platform,
    ): JsonResponse {
        $data = $this->validated($request);

        $answer = $platform->ask($request->user(), $data['question'],
            $data['conversation_id'] ?? null);

        return response()->json($this->present($answer));
    }

    /**
     * Answer in the background; the result is broadcast.
     */
    public function askLater(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $requestId = (string) Str::uuid();

        AnswerKnowledgeQuestion::dispatch($request->user(),
            $data['question'], $data['conversation_id'] ?? null,
            $requestId);

        return response()->json(['request_id' => $requestId], 202);
    }

    public function show(KnowledgeAnswer $answer): JsonResponse
    {
        Gate::authorize('view', $answer);

        return response()->json($this->present($answer));
    }

    public function feedback(
        Request $request,
        KnowledgeAnswer $answer,
    ): JsonResponse {
        Gate::authorize('rate', $answer);

        $answer->update($request->validate([
            'helpful' => ['required', 'boolean'],
            'feedback' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['status' => 'thanks']);
    }

    /**
     * @return array{question: string, conversation_id?: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'size:36'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KnowledgeAnswer $answer): array
    {
        return $answer->only([
            'id', 'reply', 'citations', 'route', 'conversation_id',
        ]);
    }
}
