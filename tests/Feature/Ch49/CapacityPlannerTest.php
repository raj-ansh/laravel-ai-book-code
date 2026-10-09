<?php

use App\Models\AiSpan;
use App\Models\AiUsage;
use App\Services\Ai\CapacityPlanner;

/** One traced run: an agent span and its usage rows. */
function tracedRun(string $agent, float $ms, array $steps): void
{
    $id = uniqid();
    AiSpan::create([
        'trace_id' => $id, 'span_id' => $id, 'kind' => 'agent',
        'name' => $agent, 'status' => 'ok', 'duration_ms' => $ms,
    ]);

    foreach ($steps as [$in, $out]) {
        AiUsage::create([
            'feature' => $agent, 'provider' => 'ollama',
            'model' => 'm', 'input_tokens' => $in,
            'output_tokens' => $out, 'cost_micros' => 0,
            'invocation_id' => $id,
        ]);
    }
}

it('applies Little\'s law with headroom', function () {
    // Mean 4 s; each run is two steps, 500 tokens in all.
    tracedRun('AccountAssistant', 3000, [[200, 30], [240, 30]]);
    tracedRun('AccountAssistant', 5000, [[200, 30], [240, 30]]);

    expect(app(CapacityPlanner::class)->plan(60, 24))->toBe([[
        'agent' => 'AccountAssistant', 'runs' => 2,
        'mean_s' => 4.0,
        'in_flight' => 4.0,     // 1 per second x 4 s
        'workers' => 6,         // x 1.5 headroom
        'tokens_per_run' => 500,
        'tokens_per_minute' => 30_000,
    ]]);
});

it('ignores traces outside the window', function () {
    tracedRun('Old', 1000, [[10, 10]]);
    $this->travel(2)->days();

    expect(app(CapacityPlanner::class)->plan(60, 24))->toBe([]);
});

it('prints a table', function () {
    tracedRun('StoreInfoAssistant', 3300, [[190, 30]]);

    $this->artisan('ai:capacity', ['--per-minute' => 30])
        ->expectsOutputToContain('StoreInfoAssistant')
        ->assertSuccessful();
});
