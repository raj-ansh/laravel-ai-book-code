<?php

use App\Ai\Agents\SupportChatAgent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);
    Sanctum::actingAs($this->asha);
});

it('streams the reply as server-sent events', function () {
    SupportChatAgent::fake(['Your order has shipped.']);

    $response = $this->postJson('/api/support/chat/stream', [
        'message' => 'Has my order shipped?',
    ]);

    $response->assertOk()
        ->assertHeader(
            'Content-Type', 'text/event-stream; charset=utf-8',
        )
        ->assertHeader('X-Conversation-Id');

    $body = $response->streamedContent();

    // One event per line, each "data: {json}", then [DONE].
    $events = collect(explode("\n\n", trim($body)))
        ->map(fn ($line) => substr($line, strlen('data: ')));

    expect($events->last())->toBe('[DONE]');

    $text = $events->slice(0, -1)
        ->map(fn ($json) => json_decode($json, true))
        ->where('type', 'text_delta')
        ->pluck('delta')
        ->implode('');

    expect($text)->toBe('Your order has shipped.');
});

it('stores the streamed turn in the conversation', function () {
    SupportChatAgent::fake(['Hello Asha.']);

    $response = $this->postJson('/api/support/chat/stream', [
        'message' => 'Hi',
    ]);
    $response->streamedContent();

    $conversation = $this->asha->conversations()->sole();

    expect($response->headers->get('X-Conversation-Id'))
        ->toBe($conversation->id)
        ->and($conversation->messages()->count())->toBe(2);
});

it('serves the chat page', function () {
    $this->get('/support/chat')
        ->assertOk()
        ->assertSee('/api/support/chat/stream');
});
