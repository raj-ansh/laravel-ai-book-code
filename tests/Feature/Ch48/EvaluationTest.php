<?php

use App\Ai\Agents\AnalyticsQuestion;
use App\Ai\Agents\StoreInfoAssistant;
use App\Evaluation\AnalyticsIntentSuite;
use App\Evaluation\EvalRunner;
use App\Evaluation\StoreFactsSuite;
use App\Models\EvalRun;
use Laravel\Ai\Exceptions\ProviderConnectionException;

/** The model always answers with this intent. */
function intentModel(array $intent): Closure
{
    return fn () => $intent + [
        'metric' => 'revenue', 'period' => 'last_month',
        'group_by' => 'none', 'status' => 'any',
    ];
}

it('scores each case by its checks and stores the run', function () {
    // Right for "revenue last month", wrong for most others.
    AnalyticsQuestion::fake(intentModel([]));

    $run = app(EvalRunner::class)
        ->run(app(AnalyticsIntentSuite::class), 'test-model');

    $first = $run->results->firstWhere('case_id', 'rev-last-month');
    $unsupported = $run->results->firstWhere('case_id', 'unsup-delete');

    expect($run)
        ->suite->toBe('analytics-intents')
        ->model->toBe('test-model')
        ->cases->toBe(20)
        ->prompt_hash->toBe(substr(sha1(
            (string) (new AnalyticsQuestion)->instructions()), 0, 12))
        ->and($first->passed)->toBeTrue()
        ->and($unsupported->passed)->toBeFalse()
        ->and($unsupported->failed_checks)->toBe(['metric'])
        ->and($run->passed)
        ->toBe($run->results->where('passed')->count());
});

it('gives partial credit field by field', function () {
    AnalyticsQuestion::fake(intentModel(['period' => 'this_month']));

    $run = app(EvalRunner::class)
        ->run(app(AnalyticsIntentSuite::class), 'test-model');

    // One field of four wrong.
    expect($run->results->firstWhere('case_id', 'rev-last-month'))
        ->passed->toBeFalse()
        ->score->toBe(0.75);
});

it('checks facts case-insensitively', function () {
    StoreInfoAssistant::fake(fn (string $q) => str_contains($q, 'gift')
        ? 'Support@acme.test can help with gift wrapping.'
        : 'Delivery is free over INR 999.');

    $run = app(EvalRunner::class)
        ->run(app(StoreFactsSuite::class), 'test-model');

    expect($run->results->firstWhere('case_id', 'unknown-giftwrap')
        ->passed)->toBeTrue()
        ->and($run->results->firstWhere('case_id', 'delivery-free')
            ->passed)->toBeTrue();
});

it('records provider failures as failed cases', function () {
    StoreInfoAssistant::fake(fn () =>
        throw ProviderConnectionException::forProvider('ollama'));

    $run = app(EvalRunner::class)
        ->run(app(StoreFactsSuite::class), 'test-model');

    expect($run->passed)->toBe(0)
        ->and($run->results->first()->failed_checks)
        ->toBe(['provider']);
});

it('shows what a change fixed and what it broke', function () {
    $runner = app(EvalRunner::class);
    $suite = app(AnalyticsIntentSuite::class);

    AnalyticsQuestion::fake(intentModel([]));
    $before = $runner->run($suite, 'model-a');
    AnalyticsQuestion::fake(intentModel(['metric' => 'unsupported']));
    $after = $runner->run($suite, 'model-b');

    $this->artisan('ai:eval-compare', [
        'before' => $before->id, 'after' => $after->id,
    ])
        ->expectsOutputToContain('fixed      unsup-delete')
        ->expectsOutputToContain('regressed  rev-last-month')
        ->assertSuccessful();
});

it('refuses to compare different suites', function () {
    AnalyticsQuestion::fake(intentModel([]));
    StoreInfoAssistant::fake(['Answer.']);

    $a = app(EvalRunner::class)
        ->run(app(AnalyticsIntentSuite::class), 'm');
    $b = app(EvalRunner::class)->run(app(StoreFactsSuite::class), 'm');

    $this->artisan('ai:eval-compare', ['before' => $a->id,
        'after' => $b->id])->assertFailed();
    expect(EvalRun::count())->toBe(2);
});
