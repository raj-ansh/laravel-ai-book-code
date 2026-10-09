<?php

use App\Ai\Agents\StoreInfoAssistant;
use App\Services\Ai\AnswerCache;
use App\Services\Ai\ProviderCircuit;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Providers\Provider;

/** A fake that fails the first $failures calls, then answers. */
function flakyProvider(int $failures, string $answer): Closure
{
    return function () use (&$failures, $answer) {
        if ($failures-- > 0) {
            throw ProviderConnectionException::forProvider('test');
        }

        return $answer;
    };
}

/** A fake where one provider is down and the others answer. */
function downProvider(string $down, string $answer): Closure
{
    return fn ($prompt, $files, Provider $provider) =>
        $provider->name() === $down
            ? throw ProviderConnectionException::forProvider($down)
            : $answer;
}

it('answers once and serves the cache after', function () {
    // One scripted answer: a second model call would fail.
    StoreInfoAssistant::fake(['Delivery takes 3-5 working days.'])
        ->preventStrayPrompts();
    $answers = app(AnswerCache::class);

    $first = $answers->answer('How long does delivery take?');
    // Same question, different case and punctuation.
    $second = $answers->answer('how long does delivery take');

    expect($first['source'])->toBe('model')
        ->and($second)->toBe([
            'text' => 'Delivery takes 3-5 working days.',
            'source' => 'cache',
        ]);
});

it('fails over to the next provider', function () {
    Event::fake([AgentFailedOver::class]);
    StoreInfoAssistant::fake(flakyProvider(1, 'Free over INR 999.'));

    $reply = StoreInfoAssistant::make()->prompt('Is delivery free?');

    expect($reply->text)->toBe('Free over INR 999.');
    Event::assertDispatched(AgentFailedOver::class,
        fn (AgentFailedOver $e) => $e->provider->name()
            === 'ollama-primary');
});

it('opens the circuit after repeated failures', function () {
    $circuit = app(ProviderCircuit::class);
    $chain = config('acme.ai.failover');

    foreach (range(1, 3) as $i) {
        $circuit->recordFailure('ollama-primary');
    }

    expect($circuit->available($chain))
        ->toBe(['ollama' => 'qwen2.5:1.5b']);

    $this->travel(121)->seconds();

    expect($circuit->available($chain))->toBe($chain);
});

it('records failovers in the circuit', function () {
    StoreInfoAssistant::fake(downProvider('ollama-primary', 'A.'));

    // Three requests, each failing on the primary first.
    foreach (range(1, 3) as $i) {
        StoreInfoAssistant::make()->prompt("Question {$i}?");
    }

    expect(app(ProviderCircuit::class)->isOpen('ollama-primary'))
        ->toBeTrue()
        ->and((new StoreInfoAssistant)->provider())
        ->toBe(['ollama' => 'qwen2.5:1.5b']);
});

it('tries the last provider when every circuit is open', function () {
    $circuit = app(ProviderCircuit::class);

    foreach (['ollama-primary', 'ollama'] as $provider) {
        foreach (range(1, 3) as $i) {
            $circuit->recordFailure($provider);
        }
    }

    expect($circuit->available(config('acme.ai.failover')))
        ->toBe(['ollama' => 'qwen2.5:1.5b']);
});

it('serves a stale answer when every provider is down', function () {
    StoreInfoAssistant::fake(['Support is open 9:00-21:00 IST.']);
    $answers = app(AnswerCache::class);
    $answers->answer('When is support open?');

    // An hour later, with no provider reachable.
    $this->travel(61)->minutes();
    StoreInfoAssistant::fake(flakyProvider(99, 'never'));

    expect($answers->answer('When is support open?'))->toBe([
        'text' => 'Support is open 9:00-21:00 IST.',
        'source' => 'stale',
    ]);
});

it('fails when there is nothing stale to serve', function () {
    StoreInfoAssistant::fake(flakyProvider(99, 'never'));

    app(AnswerCache::class)->answer('Do you ship abroad?');
})->throws(ProviderConnectionException::class);

it('starts afresh when the models change', function () {
    StoreInfoAssistant::fake(['From 1.5b.', 'From 3b.']);
    $answers = app(AnswerCache::class);
    $answers->answer('Hours?');

    config(['acme.ai.failover' => ['ollama' => 'qwen2.5:3b']]);

    expect($answers->answer('Hours?'))
        ->toBe(['text' => 'From 3b.', 'source' => 'model']);
});
