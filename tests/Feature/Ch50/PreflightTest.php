<?php

use App\Services\Ai\AiPreflight;

/** A configuration that should pass every check. */
function productionLike(): void
{
    config([
        'app.debug' => false,
        'queue.default' => 'redis',
        'cache.default' => 'redis',
        'ai.default' => 'ollama',
        'ai.default_for_embeddings' => 'ollama',
        'acme.ai.security.canary' => 'K9-2F81-QX',
    ]);
}

/** The status of one named check. */
function statusOf(string $check): string
{
    return collect(app(AiPreflight::class)->run())
        ->firstWhere('check', $check)['status'];
}

it('passes a production-like configuration', function () {
    productionLike();

    $failed = collect(app(AiPreflight::class)->run())
        ->where('status', 'fail')->pluck('check')->all();

    expect($failed)->toBe([]);
});

it('fails what would hurt in production', function (
    array $config,
    string $check,
) {
    productionLike();
    config($config);

    expect(statusOf($check))->toBe('fail');
})->with([
    'debug on' => [['app.debug' => true], 'debug_off'],
    'sync queue' => [['queue.default' => 'sync'], 'queue_async'],
    'local cache' => [['cache.default' => 'array'], 'shared_cache'],
    'published canary' => [
        ['acme.ai.security.canary' => 'ACME-7F3A9C'], 'canary_rotated'],
    'missing key' => [[
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => null,
    ], 'provider_keys'],
    'no consumer allowance' => [
        ['acme.ai.token_budgets.consumer_daily' => 0], 'token_budgets'],
]);

it('warns about a single provider and missing prices', function () {
    productionLike();
    config([
        'acme.ai.failover' => ['anthropic' => 'some-model'],
        'ai.providers.anthropic.key' => 'test-key',
    ]);

    expect(statusOf('failover_chain'))->toBe('warn')
        ->and(statusOf('pricing'))->toBe('warn')
        ->and(statusOf('provider_keys'))->toBe('pass');
});

it('exits non-zero when anything fails', function () {
    productionLike();
    config(['app.debug' => true]);

    $this->artisan('ai:preflight')->assertFailed();

    config(['app.debug' => false]);
    $this->artisan('ai:preflight')->assertSuccessful();
});
