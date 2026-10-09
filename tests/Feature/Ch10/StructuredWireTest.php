<?php

use App\Ai\Agents\TicketTriage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

it('sends the schema and decodes the JSON reply', function () {
    Http::fake(['*/api/chat' => Http::response([
        'model' => 'qwen2.5:1.5b',
        'message' => [
            'role' => 'assistant',
            'content' => json_encode([
                'reason' => 'Login problem.',
                'category' => 'account',
                'priority' => 'high',
                'requires_human' => false,
                'tags' => ['login'],
                'summary' => 'Cannot log in.',
                'customer_name' => null,
            ]),
        ],
        'done_reason' => 'stop',
    ])]);

    $response = (new TicketTriage)
        ->prompt('I cannot log in.', provider: Lab::Ollama);

    Http::assertSent(function (Request $request) {
        $format = $request['format'];

        return $format['type'] === 'object'
            && $format['additionalProperties'] === false
            && $format['properties']['priority']['enum']
                === ['low', 'normal', 'high', 'urgent']
            && in_array('summary', $format['required']);
    });

    expect($response['category'])->toBe('account')
        ->and($response['customer_name'])->toBeNull()
        ->and($response->toArray())->toHaveKey('tags');
});
