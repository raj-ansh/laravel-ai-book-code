<?php

use Laravel\Ai\AnonymousAgent;

it('returns the text the model wrote', function () {
    AnonymousAgent::fake(['Welcome to Acme, happy shopping!']);

    $this->get('/ai/hello')
        ->assertOk()
        ->assertSee('Welcome to Acme, happy shopping!');

    AnonymousAgent::assertPrompted(
        'Greet a new customer in one sentence.'
    );
});
