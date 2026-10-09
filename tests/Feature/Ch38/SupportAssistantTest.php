<?php

use App\Ai\Agents\SupportAssistant;
use App\Ai\Tools\AskPolicies;
use App\Ai\Tools\EscalateToHuman;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Rag\QuotedAnswerService;
use App\Services\Rag\RagAnswer;
use App\Services\Support\IntentRouter;
use App\Services\Support\SupportConversation;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();     // triage of escalated tickets (Chapter 13)
    $this->asha = User::factory()->create(['name' => 'Asha']);
    Order::factory()->for($this->asha)->shipped()
        ->create(['number' => 'A-1001']);
});

/** Make the router return the given intent. */
function routedAs(?string $intent): void
{
    app()->instance(IntentRouter::class, Mockery::mock(
        IntentRouter::class, ['route' => $intent],
    ));
}

/** Make the policy service return the given answer. */
function policyAnswer(RagAnswer $answer): void
{
    app()->instance(QuotedAnswerService::class, Mockery::mock(
        QuotedAnswerService::class, ['ask' => $answer],
    ));
}

it('escalates a request for a person without a model', function () {
    SupportAssistant::fake();

    $reply = app(SupportConversation::class)->reply($this->asha, null,
        'I am fed up. I want to speak to a person.');

    expect($reply['escalated'])->toBeTrue()
        ->and($reply['reply'])->toStartWith('Of course. Ticket #')
        ->and(Ticket::sole()->body)->toContain('fed up');
    SupportAssistant::assertNeverPrompted();
});

it('answers policy questions from verified RAG', function () {
    routedAs('policy');
    policyAnswer(new RagAnswer('Opened electronics: 14 days, '
        .'minus a 10% fee.', [['number' => 1, 'title' => 'Returns',
        'content' => '...']], answered: true, cited: true));
    SupportAssistant::fake();

    $reply = app(SupportConversation::class)->reply($this->asha, null,
        'Can I return opened electronics?');

    expect($reply['reply'])->toBe('Opened electronics: 14 days, '
        .'minus a 10% fee. (Source: Returns)');
    SupportAssistant::assertNeverPrompted();
});

it('says so when the policies do not answer', function () {
    routedAs('policy');
    policyAnswer(new RagAnswer('No.', [], answered: false));

    expect(app(SupportConversation::class)->reply($this->asha, null,
        'Do you price-match?')['reply'])
        ->toStartWith('I could not find that in our policies.');
});

it('lets the agent handle orders with its tools', function () {
    routedAs('order');
    SupportAssistant::fake([
        new ToolCall('call_1', 'get_order_status', [
            'order_number' => 'A-1001',
        ]),
        'Your order A-1001 has shipped.',
    ]);

    $reply = app(SupportConversation::class)->reply($this->asha, null,
        'Where is my order A-1001?');

    expect($reply['reply'])->toBe('Your order A-1001 has shipped.');
});

it('replaces claims that no tool backs up', function (string $claim) {
    routedAs(null);
    SupportAssistant::fake([$claim]);

    expect(app(SupportConversation::class)->reply($this->asha, null,
        'Help me please.')['reply'])
        ->toBe(SupportConversation::UNCONFIRMED);
})->with([
    'escalation' => "I've escalated this to a human.",
    'refund' => 'Done, your order has been refunded.',
]);

it('opens one escalation ticket, not one per message', function () {
    $tool = new EscalateToHuman($this->asha);
    $request = new Request(['reason' => 'Upset', 'summary' => 'Late.']);

    $first = (string) $tool->handle($request);
    $second = (string) $tool->handle($request);

    expect($first)->toBe($second)
        ->and(Ticket::count())->toBe(1);
});

it('gives the agent policy answers with their sources', function () {
    policyAnswer(new RagAnswer('Free above INR 1,000.', [
        ['number' => 1, 'title' => 'Shipping', 'content' => '...'],
    ], answered: true, cited: true));

    expect((string) (new AskPolicies)->handle(new Request([
        'question' => 'Is delivery free?',
    ])))->toBe("Free above INR 1,000.\nSources: Shipping");
});

it('serves the assistant to signed-in customers only', function () {
    routedAs('order');
    SupportAssistant::fake(['Hello Asha.']);

    $this->postJson('/api/support/assistant', ['message' => 'Hi'])
        ->assertUnauthorized();

    Sanctum::actingAs($this->asha);
    $this->postJson('/api/support/assistant', ['message' => 'Hi'])
        ->assertOk()
        ->assertJson(['reply' => 'Hello Asha.', 'escalated' => false]);
});

it('routes a message to its nearest example', function () {
    // [policy, order, refund] meaning; everything else is noise.
    \Laravel\Ai\Embeddings::fake(fn ($prompt) => array_map(
        fn (string $text) => match (true) {
            str_contains($text, 'return'),
            str_contains($text, 'deliver'),
            str_contains($text, 'warranty') => [1.0, 0.0, 0.0],
            str_contains($text, 'order') => [0.0, 1.0, 0.0],
            default => [0.0, 0.0, 1.0],
        },
        $prompt->inputs,
    ));
    $router = new IntentRouter(minScore: 0.6);

    expect($router->route('Can I return a lamp?'))->toBe('policy')
        ->and($router->route('Where is my order?'))->toBe('order');
});
