<?php

use App\Ai\Agents\QuickAnswer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

it('turns off thinking on Ollama only', function () {
    Http::fake(['*/api/chat' => Http::response([
        'model' => 'qwen3.5:0.8b',
        'message' => ['role' => 'assistant', 'content' => 'Yes.'],
        'done_reason' => 'stop',
    ])]);

    (new QuickAnswer)->prompt('Is PHP typed?', provider: Lab::Ollama);

    Http::assertSent(
        fn (Request $request) => $request['think'] === false
    );

    expect((new QuickAnswer)->providerOptions(Lab::OpenAI))
        ->toBe([]);
});
