<?php

use App\Ai\Agents\PolicyExpert;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('marks its instructions as cacheable', function () {
    Storage::fake('local');
    Storage::disk('local')->put(
        'policies/all.md', 'Returns: 30 days, unused items.'
    );

    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_1',
        'model' => 'claude-test',
        'content' => [['type' => 'text', 'text' => '30 days.']],
        'stop_reason' => 'end_turn',
        'usage' => [
            'input_tokens' => 12,
            'cache_read_input_tokens' => 2400,
            'output_tokens' => 4,
        ],
    ])]);

    $response = (new PolicyExpert)
        ->prompt('How long do I have to return an item?');

    Http::assertSent(function (Request $request) {
        $system = $request['system'];

        return str_contains($system[0]['text'], '30 days')
            && $system[0]['cache_control'] === [
                'type' => 'ephemeral',
            ];
    });

    expect($response->usage->cacheReadInputTokens)->toBe(2400)
        ->and($response->usage->inputTokens)->toBe(2412)
        ->and($response->usage->uncachedInputTokens())->toBe(12);
});
