<?php

use App\Ai\Agents\Explainer;
use App\Services\Ai\CustomerKeyProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends requests with the customer\'s own key', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'resp_1',
        'model' => 'gpt-test',
        'status' => 'completed',
        'output' => [[
            'type' => 'message',
            'content' => [['type' => 'output_text', 'text' => 'Hi']],
        ]],
    ])]);

    $provider = app(CustomerKeyProvider::class)
        ->openAi('sk-customer-123', 'gpt-test');

    (new Explainer)->prompt('Hello', provider: $provider->name());

    Http::assertSent(fn (Request $request) => $request->hasHeader(
        'Authorization', 'Bearer sk-customer-123'
    ) && $request['model'] === 'gpt-test');
});
