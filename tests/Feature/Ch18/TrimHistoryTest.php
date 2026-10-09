<?php

use App\Ai\Middleware\TrimHistory;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

/** Run the middleware and return the messages it sent on. */
function trimmed(array $messages, int $keep): array
{
    $step = new PendingStep(
        number: 0, isFinalStep: false,
        provider: 'ollama', model: 'qwen2.5:1.5b',
        instructions: null, messages: $messages,
        tools: [], schema: null, options: null,
    );

    $sent = [];

    $next = function (PendingStep $step) use (&$sent) {
        $sent = $step->messages;

        return new StepResult(new StepResponse(
            '', [], FinishReason::Stop, new TextUsage, new Meta,
        ));
    };

    (new TrimHistory($keep))->handle($step, $next);

    return $sent;
}

it('keeps short histories as they are', function () {
    $messages = [
        new UserMessage('Hi'), new AssistantMessage('Hello'),
    ];

    expect(trimmed($messages, keep: 12))->toBe($messages);
});

it('keeps the recent messages from a user turn on', function () {
    $messages = [
        new UserMessage('Where is A-1001?'),
        new AssistantMessage('It has shipped.'),
        new UserMessage('And A-1002?'),
        new AssistantMessage('', collect()),
        new ToolResultMessage(collect()),
        new AssistantMessage('It is packed.'),
        new UserMessage('Thanks!'),
    ];

    $sent = trimmed($messages, keep: 4);

    // Four would start at the tool result; it moves back to
    // the user message that started that turn.
    expect($sent)->toHaveCount(5)
        ->and($sent[0]->content)->toBe('And A-1002?')
        ->and($sent[0]->role)->toBe(MessageRole::User);
});
