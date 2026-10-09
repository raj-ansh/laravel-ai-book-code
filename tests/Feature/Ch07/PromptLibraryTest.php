<?php

use App\Ai\Prompts\PromptLibrary;

beforeEach(fn () => $this->prompts = app(PromptLibrary::class));

it('renders the active version with its data', function () {
    $prompt = $this->prompts->get('manager-briefing', [
        'audience' => 'a finance director',
    ]);

    expect($prompt->version)->toBe('v2')
        ->and($prompt->text)
        ->toContain('briefings for a finance director at Acme')
        ->toContain('Do not invent facts');
});

it('switches version through configuration', function () {
    config(['acme.ai.prompts.manager-briefing' => 'v1']);

    expect((string) $this->prompts->get('manager-briefing'))
        ->toBe('Summarize this.');
});

it('refuses to render with a missing value', function () {
    $this->prompts->get('manager-briefing');
})->throws(InvalidArgumentException::class, 'needs [audience]');

it('refuses unknown versions and unsafe names', function (
    string $name, ?string $version,
) {
    expect(fn () => $this->prompts->get($name, [], $version))
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['manager-briefing', 'v99'],
    ['../../.env', null],
]);
