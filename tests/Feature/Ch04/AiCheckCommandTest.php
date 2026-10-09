<?php

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;

it('passes when both providers answer', function () {
    AnonymousAgent::fake(['pong']);
    Embeddings::fake();

    $this->artisan('ai:check')
        ->expectsOutputToContain('text: openai')
        ->expectsOutputToContain('1536 dimensions')
        ->assertSuccessful();
});

it('fails when a provider is down', function () {
    AnonymousAgent::fake(function () {
        throw new RuntimeException('Connection refused');
    });

    $this->artisan('ai:check --skip-embeddings')
        ->expectsOutputToContain('Connection refused')
        ->assertFailed();
});
