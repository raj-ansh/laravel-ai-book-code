<?php

use App\Ai\Agents\SupportAgent;
use App\Models\Order;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;

it('shows each step the agent took', function () {
    $asha = User::factory()->create([
        'email' => 'asha@example.com',
    ]);
    Order::factory()->for($asha)->create();

    SupportAgent::fake([
        new ToolCall('call_1', 'FindCustomerOrders', []),
        'It is being prepared.',
    ]);

    $this->artisan('support:ask', [
        'email' => 'asha@example.com',
        'question' => 'Where is my order?',
    ])
        ->expectsOutputToContain(
            'step 1: tool_calls FindCustomerOrders([])'
        )
        ->expectsOutputToContain('step 2: stop')
        ->expectsOutputToContain('It is being prepared.')
        ->expectsOutputToContain('2 steps')
        ->assertSuccessful();
});
