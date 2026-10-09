<?php

use App\Ai\Agents\Explainer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

use function Laravel\Ai\agent;

beforeEach(function () {
    config(['ai.default' => 'ollama']);

    // What Ollama's /api/chat endpoint sends back.
    Http::fake(['*/api/chat' => Http::response([
        'model' => 'qwen3.5:4b',
        'message' => [
            'role' => 'assistant',
            'content' => 'A queue runs slow work later.',
        ],
        'done_reason' => 'stop',
        'prompt_eval_count' => 42,
        'eval_count' => 9,
    ])]);
});

it('sends instructions as the system message', function () {
    $response = (new Explainer)
        ->prompt('What is a queue?');

    Http::assertSent(function (Request $request) {
        $messages = $request['messages'];

        return $messages[0]['role'] === 'system'
            && str_contains($messages[0]['content'],
                'plain English')
            && $messages[1] === [
                'role' => 'user',
                'content' => 'What is a queue?',
            ]
            && $request['options']['temperature'] === 0.2
            && $request['options']['num_predict'] === 300;
    });

    expect($response->text)
        ->toBe('A queue runs slow work later.')
        ->and($response->usage->inputTokens)->toBe(42)
        ->and($response->usage->outputTokens)->toBe(9)
        ->and($response->meta->model)->toBe('qwen3.5:4b');
});

it('sends the whole history on every call', function () {
    agent(
        instructions: 'You are terse.',
        messages: [
            new UserMessage('My name is Asha.'),
            new AssistantMessage('Hi Asha.'),
        ],
    )->prompt('What is my name?');

    Http::assertSent(fn (Request $request) => array_column(
        $request['messages'], 'role'
    ) === ['system', 'user', 'assistant', 'user']);
});
