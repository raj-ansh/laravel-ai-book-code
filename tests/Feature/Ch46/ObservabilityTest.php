<?php

use App\Ai\Agents\OrdersSpecialist;
use App\Ai\Agents\StoreInfoAssistant;
use App\Ai\Agents\SupportConcierge;
use App\Models\AiSpan;
use App\Models\User;
use App\Services\Ai\AiHealth;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\ToolCall;

/** A span row, for the health tests. */
function span(string $name, float $ms, string $status = 'ok'): void
{
    AiSpan::create([
        'trace_id' => 't', 'span_id' => uniqid(), 'kind' => 'agent',
        'name' => $name, 'status' => $status, 'duration_ms' => $ms,
    ]);
}

it('traces agents, steps and tools as one tree', function () {
    SupportConcierge::fake([
        new ToolCall('call_1', 'ask_orders_specialist', [
            'task' => 'Status of A-1001?',
        ]),
        'Your order is on its way.',
    ]);
    OrdersSpecialist::fake(['A-1001 has shipped.']);

    SupportConcierge::make(customer: User::factory()->create())
        ->prompt('Where is A-1001?');

    $spans = AiSpan::all();
    $root = $spans->firstWhere('name', 'SupportConcierge');
    $tool = $spans->firstWhere('kind', 'tool');
    $child = $spans->firstWhere('name', 'OrdersSpecialist');

    expect($spans->pluck('trace_id')->unique()->all())
        ->toBe([$root->span_id])
        ->and($root->parent_id)->toBeNull()
        ->and($tool->name)->toBe('ask_orders_specialist')
        ->and($tool->parent_id)->toBe($root->span_id)
        ->and($child->parent_id)->toBe($tool->span_id)
        ->and($spans->where('kind', 'step'))->toHaveCount(3);

    $this->artisan('ai:trace')
        ->expectsOutputToContain('tool ask_orders_specialist')
        ->assertSuccessful();
});

it('records a failed step next to the one that worked', function () {
    StoreInfoAssistant::fake(fn ($prompt, $files, Provider $p) =>
        $p->name() === 'ollama-primary'
            ? throw ProviderConnectionException::forProvider('x')
            : 'Cards and UPI.');

    StoreInfoAssistant::make()->prompt('How can I pay?');

    $steps = AiSpan::where('kind', 'step')->orderBy('id')->get();

    expect($steps->pluck('status')->all())->toBe(['error', 'ok'])
        ->and($steps[0]->name)
        ->toBe('step 0: ollama-primary/qwen2.5:1.5b')
        ->and($steps[0]->error)
        ->toContain('ProviderConnectionException')
        ->and(AiSpan::firstWhere('kind', 'agent')->status)->toBe('ok');
});

it('marks the agent failed when every provider fails', function () {
    StoreInfoAssistant::fake(fn () =>
        throw ProviderConnectionException::forProvider('x'));

    try {
        StoreInfoAssistant::make()->prompt('How can I pay?');
    } catch (ProviderConnectionException) {
    }

    expect(AiSpan::firstWhere('kind', 'agent')->status)->toBe('error');
});

it('puts the trace id on every log line during a run', function () {
    $seen = null;
    StoreInfoAssistant::fake(function () use (&$seen) {
        $seen = Context::get('ai_trace_id');

        return 'Answer.';
    });

    StoreInfoAssistant::make()->prompt('Hours?');

    expect($seen)->toBe(AiSpan::firstWhere('kind', 'agent')->span_id)
        ->and(Context::has('ai_trace_id'))->toBeFalse();
});

it('reports calls, errors and percentiles', function () {
    foreach ([100, 200, 300, 400, 5000] as $ms) {
        span('StoreInfoAssistant', $ms);
    }
    span('StoreInfoAssistant', 50, 'error');

    expect(app(AiHealth::class)->lastMinutes(60))->toBe([[
        'name' => 'agent:StoreInfoAssistant', 'calls' => 6,
        'error_rate' => 0.167, 'p50_ms' => 200.0, 'p95_ms' => 5000.0,
    ]]);
});

it('prunes spans after two weeks', function () {
    span('Old', 1);
    $this->travel(15)->days();
    span('New', 1);

    $this->artisan('model:prune', ['--model' => AiSpan::class]);

    expect(AiSpan::pluck('name')->all())->toBe(['New']);
});
