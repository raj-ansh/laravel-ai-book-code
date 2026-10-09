<?php

use App\Ai\Agents\SupportDeskAgent;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);
    $this->staff = User::factory()->staff()->create();

    Order::factory()->for($this->asha)->create([
        'number' => 'A-1001', 'total_cents' => 459900,
    ]);
    Order::factory()->for($this->asha)->create([
        'number' => 'A-1002', 'total_cents' => 79900,
    ]);
});

/** What Ollama's /api/chat returns for a tool call or text. */
function ollamaReply(?array $toolCall = null, string $text = ''): array
{
    return [
        'model' => 'qwen2.5:1.5b',
        'message' => array_filter([
            'role' => 'assistant',
            'content' => $text,
            'tool_calls' => $toolCall ? [$toolCall] : null,
        ]),
        'done_reason' => 'stop',
    ];
}

it('pauses a large refund for staff approval', function () {
    Event::fake([ToolApprovalRequested::class]);
    SupportDeskAgent::fake([
        new ToolCall('call_1', 'refund_order', [
            'order_number' => 'A-1001', 'reason' => 'Broken',
        ]),
    ]);
    Sanctum::actingAs($this->asha);

    $this->postJson('/api/support/desk', [
        'message' => 'Please refund A-1001, it arrived broken.',
    ])->assertOk()->assertJson([
        'awaiting_approval' => true,
        'reply' => 'Your request is waiting for a member of staff.',
    ]);

    expect(Refund::count())->toBe(0);
    Event::assertDispatched(ToolApprovalRequested::class);
});

it('refunds a small order without asking anyone', function () {
    SupportDeskAgent::fake([
        new ToolCall('call_1', 'refund_order', [
            'order_number' => 'A-1002', 'reason' => 'Broken',
        ]),
        'Done: INR 799.00 is on its way back.',
    ]);
    Sanctum::actingAs($this->asha);

    $this->postJson('/api/support/desk', [
        'message' => 'Refund A-1002 please, it is broken.',
    ])->assertJson(['awaiting_approval' => false]);

    expect(Refund::sole()->amount_cents)->toBe(79900);
});

it('only lets staff see and decide approvals', function () {
    Sanctum::actingAs($this->asha);

    $this->getJson('/api/staff/approvals/any-id')
        ->assertForbidden();
    $this->postJson('/api/staff/approvals/any-id', [
        'decisions' => [['id' => 'call_1', 'approve' => true]],
    ])->assertForbidden();
});

it('runs the refund once a person approves it', function (
    bool $approve, int $refunds,
) {
    config(['ai.default' => 'ollama']);

    Http::fake(['*/api/chat' => Http::sequence()
        ->push(ollamaReply(toolCall: ['id' => 'call_1', 'function' => [
            'name' => 'refund_order',
            'arguments' => [
                'order_number' => 'A-1001', 'reason' => 'Broken',
            ],
        ]]))
        ->push(ollamaReply(text: 'Your refund has been handled.')),
    ]);

    Sanctum::actingAs($this->asha);
    $conversation = $this->postJson('/api/support/desk', [
        'message' => 'Refund A-1001, it arrived broken.',
    ])->json('conversation_id');

    Sanctum::actingAs($this->staff);
    $this->getJson("/api/staff/approvals/{$conversation}")
        ->assertJsonPath('pending.0.tool', 'refund_order')
        ->assertJsonPath('pending.0.arguments.order_number', 'A-1001');

    $this->postJson("/api/staff/approvals/{$conversation}", [
        'decisions' => [[
            'id' => 'call_1',
            'approve' => $approve,
            'note' => 'Photos show no damage.',
        ]],
    ])->assertOk()->assertJson([
        'reply' => 'Your refund has been handled.',
    ]);

    expect(Refund::count())->toBe($refunds);
})->with([
    'approved' => [true, 1],
    'rejected' => [false, 0],
]);
