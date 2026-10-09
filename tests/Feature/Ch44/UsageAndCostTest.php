<?php

use App\Ai\Agents\AccountAssistant;
use App\Models\AiUsage;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Ai\AiPricing;
use App\Services\Ai\TokenBudget;
use App\Support\AiContext;
use Laravel\Ai\Ai;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['acme.ai.pricing.openai' => [
        'input' => 0.40, 'output' => 1.60, // Example prices.
    ]]);

    $this->northwood = Organisation::create([
        'name' => 'Northwood Stores', 'ai_plan' => 'basic',
    ]);
    $this->karan = User::factory()->create([
        'organisation_id' => $this->northwood->id,
    ]);
});

/** A completed step, as the SDK reports it. */
function completedStep(
    string $provider,
    int $in,
    int $out,
): StepCompleted {
    return new StepCompleted(
        invocationId: 'inv-1', stepNumber: 0,
        agent: new AccountAssistant(User::factory()->make()),
        provider: Ai::textProvider($provider),
        model: 'gpt-example', isFinalStep: true,
        response: new StepResponse('Done.', [], FinishReason::Stop,
            new TextUsage($in, $out), new Meta),
        time: 0.5,
    );
}

/** Usage already recorded for a user this month. */
function usedTokens(User $user, int $tokens): void
{
    AiUsage::create([
        'user_id' => $user->id,
        'organisation_id' => $user->organisation_id,
        'feature' => 'AccountAssistant', 'provider' => 'ollama',
        'model' => 'qwen2.5:1.5b', 'input_tokens' => $tokens,
        'output_tokens' => 0, 'cost_micros' => 0,
        'invocation_id' => 'earlier',
    ]);
}

it('prices tokens from the table', function () {
    $pricing = app(AiPricing::class);

    // 2,000 x $0.40 + 500 x $1.60 per million = $0.0016.
    expect($pricing->costMicros('openai', 'gpt-x', 2000, 500))
        ->toBe(1600)
        ->and($pricing->costMicros('ollama', 'qwen', 2000, 500))
        ->toBe(0);
});

it('records each step for the user in context', function () {
    AiContext::forUser($this->karan);

    event(completedStep('openai', 2000, 500));

    expect(AiUsage::sole())
        ->user_id->toBe($this->karan->id)
        ->organisation_id->toBe($this->northwood->id)
        ->feature->toBe('AccountAssistant')
        ->input_tokens->toBe(2000)
        ->cost_micros->toBe(1600);
});

it('meters real agent calls through the API', function () {
    Order::factory()->for($this->karan)->create(['number' => 'A-4001']);
    AccountAssistant::fake(['A-4001 has shipped.']);
    Sanctum::actingAs($this->karan);

    $this->postJson('/api/business/assistant', [
        'message' => 'Where is A-4001?',
    ])->assertOk();

    expect(AiUsage::sole()->organisation_id)
        ->toBe($this->northwood->id);
});

it('stops a company over its monthly allowance', function () {
    config(['acme.ai.token_budgets.basic' => 1000]);
    AccountAssistant::fake()->preventStrayPrompts();
    usedTokens($this->karan, 1000);
    Sanctum::actingAs($this->karan);

    $this->postJson('/api/business/assistant', ['message' => 'Hi'])
        ->assertStatus(429);
});

it('counts the company, not the user', function () {
    config(['acme.ai.token_budgets.basic' => 1000]);
    $colleague = User::factory()->create([
        'organisation_id' => $this->northwood->id,
    ]);
    usedTokens($this->karan, 600);
    usedTokens($colleague, 500);

    expect(app(TokenBudget::class)->status($colleague))
        ->toBe(['limit' => 1000, 'used' => 1100])
        ->and(app(TokenBudget::class)->exceeded($colleague))
        ->toBeTrue();
});

it('gives consumers a daily allowance, staff none', function () {
    $asha = User::factory()->create();
    usedTokens($asha, 500);
    $this->travel(1)->days();

    expect(app(TokenBudget::class)->status($asha)['used'])->toBe(0)
        ->and(app(TokenBudget::class)->status(
            User::factory()->create(['is_staff' => true]),
        )['limit'])->toBeNull();
});

it('limits requests per user per minute', function () {
    AccountAssistant::fake(array_fill(0, 11, 'Hello.'));
    Sanctum::actingAs($this->karan);

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/business/assistant', ['message' => 'Hi'])
            ->assertOk();
    }

    $this->postJson('/api/business/assistant', ['message' => 'Hi'])
        ->assertTooManyRequests();
});

it('reports usage to staff only', function () {
    AiContext::forUser($this->karan);
    event(completedStep('openai', 2000, 500));

    Sanctum::actingAs($this->karan);
    $this->getJson('/api/staff/ai-usage')->assertForbidden();

    Sanctum::actingAs(User::factory()->create(['is_staff' => true]));
    $this->getJson('/api/staff/ai-usage?days=7')->assertOk()
        ->assertJsonPath('organisations.0', [
            'name' => 'Northwood Stores', 'calls' => 1,
            'tokens' => 2500, 'cost_usd' => 0.0016,
        ])
        ->assertJsonPath('features.0.name', 'AccountAssistant');
});
