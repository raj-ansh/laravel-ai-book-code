<?php

use App\Ai\Agents\FaqAnswerer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Events\AgentFailedOver;

it('fails over when a provider is overloaded', function () {
    Event::fake([AgentFailedOver::class]);

    Http::fake([
        // Anthropic answers 529: overloaded.
        'api.anthropic.com/*' => Http::response([
            'type' => 'error',
            'error' => ['type' => 'overloaded_error'],
        ], 529),

        // OpenAI's Responses API answers normally.
        'api.openai.com/*' => Http::response([
            'id' => 'resp_1',
            'model' => 'gpt-test',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'Orders ship within two days.',
                ]],
            ]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 7],
        ]),
    ]);

    $response = (new FaqAnswerer)
        ->prompt('How fast do you ship?');

    expect($response->text)->toBe('Orders ship within two days.')
        ->and($response->meta->provider)->toBe('openai');

    Event::assertDispatched(
        AgentFailedOver::class,
        fn (AgentFailedOver $event) => $event->provider
            ->name() === 'anthropic'
    );
});

it('gives up when every provider fails', function () {
    Http::fake(['*' => Http::response([], 503)]);

    (new FaqAnswerer)->prompt('How fast do you ship?');
})->throws(Laravel\Ai\Exceptions\ProviderOverloadedException::class);
