<?php

use App\Ai\Agents\ReplyDrafter;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Prompts\AgentPrompt;

it('drafts a reply to a customer message', function () {
    ReplyDrafter::fake([
        "Hi Asha, sorry about the delay...\n",
    ]);

    $this->postJson('/api/support/reply-drafts', [
        'message' => 'Where is my parcel?',
        'customer_name' => 'Asha',
    ])
        ->assertCreated()
        ->assertJson([
            'draft' => 'Hi Asha, sorry about the delay...',
        ]);

    ReplyDrafter::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->contains(
            'Where is my parcel?'
        ) && $prompt->contains('Customer name: Asha')
    );
});

it('never calls the model for invalid input', function () {
    ReplyDrafter::fake();

    $this->postJson('/api/support/reply-drafts', [
        'message' => str_repeat('x', 4001),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    ReplyDrafter::assertNeverPrompted();
});

it('limits each client to ten drafts a minute', function () {
    ReplyDrafter::fake();

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/support/reply-drafts', [
            'message' => "Question {$i}",
        ])->assertCreated();
    }

    $this->postJson('/api/support/reply-drafts', [
        'message' => 'One more',
    ])->assertTooManyRequests();

    ReplyDrafter::assertPromptedTimes(10);
});

it('answers 503 when the provider is unavailable', function () {
    ReplyDrafter::fake(function () {
        throw ProviderConnectionException::forProvider('openai');
    });

    $this->postJson('/api/support/reply-drafts', [
        'message' => 'Where is my parcel?',
    ])->assertServiceUnavailable()
        ->assertJson([
            'message' => 'The assistant is unavailable. '
                .'Please try again in a moment.',
        ]);
});
