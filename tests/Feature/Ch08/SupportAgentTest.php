<?php

use App\Ai\Agents\SupportAgent;
use App\Models\Order;
use App\Models\User;
use Laravel\Ai\Exceptions\NoSuchToolException;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);

    Order::factory()->for($this->asha)->shipped()
        ->create(['number' => 'A-1001']);

    // Somebody else's order: the agent must never see it.
    Order::factory()->create(['number' => 'B-2002']);
});

it('addresses the customer it was built for', function () {
    $agent = SupportAgent::make(customer: $this->asha);

    expect((string) $agent->instructions())
        ->toContain('You are talking to Asha.');
});

it('calls its tool, then answers from the result', function () {
    SupportAgent::fake([
        new ToolCall('call_1', 'FindCustomerOrders', ['limit' => 3]),
        'Your order A-1001 shipped with BlueDart.',
    ]);

    $response = SupportAgent::make(customer: $this->asha)
        ->prompt('Where is my order?');

    expect($response->text)
        ->toBe('Your order A-1001 shipped with BlueDart.')
        ->and($response->steps)->toHaveCount(2)
        ->and($response->toolCalls)->toHaveCount(1);

    // The tool really ran, against the test database.
    expect($response->toolResults->first()->text())
        ->toContain('A-1001')
        ->toContain('BlueDart')
        ->not->toContain('B-2002');
});

it('stops after its maximum number of steps', function () {
    $calls = 0;

    SupportAgent::fake(function () use (&$calls) {
        return new ToolCall(
            'call_'.(++$calls), 'FindCustomerOrders', [],
        );
    });

    $response = SupportAgent::make(customer: $this->asha)
        ->prompt('Where is my order?');

    expect($calls)->toBe(4)
        ->and($response->steps)->toHaveCount(4)
        ->and($response->text)->toBe('')
        ->and($response->steps->last()->finishReason)
        ->toBe(FinishReason::ToolCalls);
});

it('refuses a tool the agent does not have', function () {
    SupportAgent::fake([
        new ToolCall('call_1', 'DeleteEverything', []),
    ]);

    SupportAgent::make(customer: $this->asha)->prompt('Hi');
})->throws(NoSuchToolException::class);

it('keeps a bad argument inside safe bounds', function () {
    SupportAgent::fake([
        new ToolCall('call_1', 'FindCustomerOrders', [
            'limit' => 'many',
        ]),
        'Done.',
    ]);

    $response = SupportAgent::make(customer: $this->asha)
        ->prompt('Show my orders');

    expect($response->toolResults->first()->text())
        ->toContain('A-1001');
});
