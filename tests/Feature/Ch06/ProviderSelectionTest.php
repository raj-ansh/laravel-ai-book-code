<?php

use App\Ai\Agents\Explainer;
use App\Ai\Agents\ProductCopywriter;
use App\Ai\Agents\TicketSummarizer;
use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;

it('uses the default provider when nothing is set', function () {
    ProductCopywriter::fake();

    (new ProductCopywriter)->prompt('A brass desk lamp');

    ProductCopywriter::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->provider
            ->name() === 'openai'
    );
});

it('reads its provider and model from config', function () {
    config(['acme.ai.copywriter' => [
        'provider' => 'ollama',
        'model' => 'qwen2.5:1.5b',
    ]]);

    ProductCopywriter::fake();

    (new ProductCopywriter)->prompt('A brass desk lamp');

    ProductCopywriter::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->provider
            ->name() === 'ollama'
            && $prompt->model === 'qwen2.5:1.5b'
    );
});

it('asks for the cheapest model of its provider', function () {
    TicketSummarizer::fake();

    (new TicketSummarizer)->prompt('Parcel late, wants refund.');

    $cheapest = Ai::textProvider('openai')->cheapestTextModel();

    TicketSummarizer::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->model === $cheapest
    );
});

it('lets one call override provider and model', function () {
    Explainer::fake();

    (new Explainer)->prompt(
        'What is a queue?',
        provider: Lab::Ollama,
        model: 'qwen3.5:0.8b',
    );

    Explainer::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->provider
            ->name() === 'ollama'
            && $prompt->model === 'qwen3.5:0.8b'
    );
});
