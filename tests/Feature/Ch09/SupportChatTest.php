<?php

use App\Ai\Agents\SupportChatAgent;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->asha = User::factory()->create(['name' => 'Asha']);
});

it('starts a conversation and stores both turns', function () {
    SupportChatAgent::fake(['Hi Asha, how can I help?']);
    Sanctum::actingAs($this->asha);

    $response = $this->postJson('/api/support/chat', [
        'message' => 'Hello!',
    ])->assertOk()
        ->assertJson(['reply' => 'Hi Asha, how can I help?']);

    $conversation = $this->asha->conversations()->sole();

    expect($response->json('conversation_id'))
        ->toBe($conversation->id)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->title)->toBe('Hello!');
});

it('sends the earlier turns with the next message', function () {
    SupportChatAgent::fake(['Nice to meet you.', 'You are Asha.']);
    Sanctum::actingAs($this->asha);

    $id = $this->postJson('/api/support/chat', [
        'message' => 'My name is Asha.',
    ])->json('conversation_id');

    $this->postJson('/api/support/chat', [
        'message' => 'What is my name?',
        'conversation_id' => $id,
    ])->assertOk()->assertJson(['conversation_id' => $id]);

    SupportChatAgent::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->prompt
            === 'What is my name?'
            && $prompt->agent->currentConversation() === $id
    );

    expect($this->asha->conversations()->sole()->messages()->count())
        ->toBe(4);
});

it('will not continue someone else\'s conversation', function () {
    SupportChatAgent::fake(['Hello.']);
    Sanctum::actingAs($this->asha);

    $ashas = $this->postJson('/api/support/chat', [
        'message' => 'My address is 12 Park Street.',
    ])->json('conversation_id');

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/support/chat', [
        'message' => 'What address did I give you?',
        'conversation_id' => $ashas,
    ])->assertNotFound();

    $this->getJson("/api/support/chat/{$ashas}")
        ->assertNotFound();

    SupportChatAgent::assertPromptedTimes(1);
});

it('requires a signed-in user', function () {
    SupportChatAgent::fake();

    $this->postJson('/api/support/chat', ['message' => 'Hi'])
        ->assertUnauthorized();

    SupportChatAgent::assertNeverPrompted();
});

it('shows the stored messages in order', function () {
    SupportChatAgent::fake(['Hello Asha.']);
    Sanctum::actingAs($this->asha);

    $id = $this->postJson('/api/support/chat', [
        'message' => 'Hi there',
    ])->json('conversation_id');

    $this->getJson("/api/support/chat/{$id}")
        ->assertOk()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'Hi there')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Hello Asha.');
});
