<?php

use App\Ai\Agents\OrdersSpecialist;
use App\Ai\Agents\PolicySpecialist;
use App\Ai\Agents\SupportConcierge;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);
});

it('delegates to the right specialist', function () {
    SupportConcierge::fake([
        new ToolCall('call_1', 'ask_orders_specialist', [
            'task' => 'What is the status of order A-1001?',
        ]),
        'Your order A-1001 is on its way.',
    ]);
    OrdersSpecialist::fake(['A-1001 shipped with BlueDart.']);
    PolicySpecialist::fake();

    $response = SupportConcierge::make(customer: $this->asha)
        ->prompt('Where is A-1001?');

    expect($response->text)->toBe('Your order A-1001 is on its way.')
        ->and($response->toolResults->first()->text())
        ->toBe('A-1001 shipped with BlueDart.');
    OrdersSpecialist::assertPrompted(
        'What is the status of order A-1001?'
    );
    PolicySpecialist::assertNeverPrompted();
});

it('survives a specialist that fails', function () {
    SupportConcierge::fake([
        new ToolCall('call_1', 'ask_orders_specialist', [
            'task' => 'Status of A-1001?',
        ]),
        'Sorry, I cannot check orders right now.',
    ]);
    OrdersSpecialist::fake(function () {
        throw new RuntimeException('Provider unavailable');
    });

    $response = SupportConcierge::make(customer: $this->asha)
        ->prompt('Where is A-1001?');

    expect($response->toolResults->first()->text())
        ->toBe('Agent failed: Provider unavailable')
        ->and($response->text)
        ->toBe('Sorry, I cannot check orders right now.');
});

it('gives only the policy specialist the handbook', function () {
    $concierge = SupportConcierge::make(customer: $this->asha);

    expect((string) (new PolicySpecialist)->instructions())
        ->toContain('within 30 days of delivery')
        ->and((string) $concierge->instructions())
        ->not->toContain('30 days');
});
