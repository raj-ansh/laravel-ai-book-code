<?php

use App\Ai\Agents\LaravelDocsResearcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

it('asks the provider to search and fetch for it', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_1',
        'model' => 'claude-test',
        'stop_reason' => 'end_turn',
        'content' => [
            [
                'type' => 'web_search_tool_result',
                'search_results' => [[
                    'url' => 'https://laravel.com/docs/13.x/queues',
                    'title' => 'Queues - Laravel 13.x',
                ]],
            ],
            [
                'type' => 'text',
                'text' => 'Use the afterCommit method.',
            ],
        ],
        'usage' => ['input_tokens' => 900, 'output_tokens' => 20],
    ])]);

    $response = (new LaravelDocsResearcher)
        ->prompt('How do I dispatch a job after commit?');

    Http::assertSent(function (Request $request) {
        [$search, $fetch] = $request['tools'];

        return $search['type'] === 'web_search_20250305'
            && $search['max_uses'] === 3
            && $search['allowed_domains'] === ['laravel.com']
            && $fetch['name'] === 'web_fetch';
    });

    expect($response->text)->toBe('Use the afterCommit method.')
        ->and($response->meta->citations->first()->url)
        ->toBe('https://laravel.com/docs/13.x/queues');
});

it('refuses provider tools on a provider without them', function () {
    Http::fake();

    (new LaravelDocsResearcher)
        ->prompt('Anything', provider: Lab::Ollama);
})->throws(
    RuntimeException::class,
    'Ollama does not support [WebSearch] provider tools.',
);
