<?php

use App\Ai\Agents\DocumentAnswerer;
use App\Services\Documents\HandbookAnswerService;
use Laravel\Ai\Prompts\AgentPrompt;

it('puts every policy file in the prompt', function () {
    DocumentAnswerer::fake(['One year.']);

    $response = app(HandbookAnswerService::class)
        ->ask('How long is the lamp warranty?');

    expect($response->text)->toBe('One year.');

    DocumentAnswerer::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->contains(
            '<document path="policies/warranty.md">'
        ) && $prompt->contains(
            '<document path="policies/shipping.md">'
        ) && $prompt->contains(
            'Question: How long is the lamp warranty?'
        )
    );
});
