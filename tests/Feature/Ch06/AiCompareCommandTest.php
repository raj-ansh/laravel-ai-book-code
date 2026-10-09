<?php

use App\Ai\Agents\QuickAnswer;
use Illuminate\Support\Facades\Artisan;

it('runs the prompt against each target', function () {
    QuickAnswer::fake(['Paris.', 'It is Paris.']);

    Artisan::call('ai:compare', [
        'prompt' => 'Capital of France?',
        'targets' => ['openai:model-a', 'ollama:model-b'],
    ]);

    expect(Artisan::output())
        ->toContain('openai:model-a')
        ->toContain('It is Paris.');

    QuickAnswer::assertPromptedTimes(2);
});
