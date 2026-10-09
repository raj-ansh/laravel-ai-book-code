<?php

use App\Ai\Agents\Explainer;

it('prints the answer and its usage', function () {
    Explainer::fake(['Tokens are chunks of text.']);

    $this->artisan('ai:anatomy', ['question' => 'What is a token?'])
        ->expectsOutputToContain('Tokens are chunks of text.')
        ->expectsOutputToContain('input tokens')
        ->assertSuccessful();

    Explainer::assertPrompted('What is a token?');
});
