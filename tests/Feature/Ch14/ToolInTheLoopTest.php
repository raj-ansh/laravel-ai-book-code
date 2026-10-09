<?php

use App\Ai\Agents\SupportAgent;
use App\Ai\Tools\OrderStatusTool;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->asha = User::factory()->create();
    Order::factory()->for($this->asha)->shipped()
        ->create(['number' => 'A-1001']);
});

it('lets the model correct a rejected argument', function () {
    SupportAgent::fake([
        new ToolCall('call_1', 'get_order_status', [
            'order_number' => '1001',
        ]),
        new ToolCall('call_2', 'get_order_status', [
            'order_number' => 'A-1001',
        ]),
        'Order A-1001 has shipped with BlueDart.',
    ]);

    $response = SupportAgent::make(customer: $this->asha)
        ->withTools(fn (array $tools) => [
            ...$tools, new OrderStatusTool($this->asha),
        ])
        ->prompt('Where is order 1001?');

    [$rejected, $accepted] = $response->toolResults->all();

    expect($rejected->text())
        ->toBe('The order number field format is invalid.')
        ->and($accepted->text())->toContain('"status":"shipped"')
        ->and($response->text)->toContain('shipped');
});

it('describes the tool to the model', function () {
    Http::fake(['*/api/chat' => Http::response([
        'model' => 'qwen2.5:1.5b',
        'message' => ['role' => 'assistant', 'content' => 'Hi.'],
        'done_reason' => 'stop',
    ])]);

    (new SupportAgent($this->asha))
        ->withTools([new OrderStatusTool($this->asha)])
        ->prompt('Hello', provider: Lab::Ollama);

    Http::assertSent(function (Request $request) {
        $tool = $request['tools'][0]['function'];

        return $tool['name'] === 'get_order_status'
            && str_contains($tool['description'], 'order number')
            && $tool['parameters']['properties']['order_number']
                ['pattern'] === '^A-\d{4}$'
            && $tool['parameters']['required'] === ['order_number'];
    });
});
