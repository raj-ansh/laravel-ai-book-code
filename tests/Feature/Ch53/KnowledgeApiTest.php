<?php

use App\Ai\Agents\KnowledgeAssistant;
use App\Events\KnowledgeAnswerReady;
use App\Jobs\AnswerKnowledgeQuestion;
use App\Models\KnowledgeAnswer;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Rag\QuotedAnswerService;
use App\Services\Rag\RagAnswer;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->priya = User::factory()->create([
        'organisation_id' => Organisation::create([
            'name' => 'Brightlite', 'ai_plan' => 'pro'])->id,
    ]);

    // The knowledge base always finds this verified answer.
    $this->mock(QuotedAnswerService::class)
        ->shouldReceive('ask')->andReturn(new RagAnswer(
            'Tuesdays and Fridays.', [[
                'number' => 1, 'title' => 'Brightlite trade terms',
                'content' => 'Bulk deliveries go to Brightlite\'s Pune '
                    .'warehouse on Tuesdays and Fridays.',
            ]], answered: true, cited: true));
});

it('answers with citations through the API', function () {
    KnowledgeAssistant::fake(['From memory.']);
    Sanctum::actingAs($this->priya);

    $this->postJson('/api/knowledge/ask', [
        'question' => 'Which days do bulk deliveries arrive?',
    ])->assertOk()
        ->assertJsonPath('route', 'fallback')
        ->assertJsonPath('reply', 'Tuesdays and Fridays.')
        ->assertJsonPath('citations.0.title', 'Brightlite trade terms');
});

it('is a pro feature', function () {
    Sanctum::actingAs(User::factory()->create([
        'organisation_id' => Organisation::create([
            'name' => 'Northwood', 'ai_plan' => 'basic'])->id,
    ]));

    $this->postJson('/api/knowledge/ask', ['question' => 'Hi?'])
        ->assertForbidden();
});

it('answers later on the queue, for the same person', function () {
    Queue::fake();
    Sanctum::actingAs($this->priya);

    $id = $this->postJson('/api/knowledge/ask-later', [
        'question' => 'Which days do bulk deliveries arrive?',
    ])->assertAccepted()->json('request_id');

    Queue::assertPushedOn('ai-interactive',
        AnswerKnowledgeQuestion::class,
        fn ($job) => $job->requestId === $id
            && $job->user->is($this->priya));
    // MeterAi set the context the job will carry.
    expect(Context::get('ai_user_id'))->toBe($this->priya->id);
});

it('broadcasts the checked answer when the job is done', function () {
    Event::fake([KnowledgeAnswerReady::class]);
    KnowledgeAssistant::fake(['From memory.']);

    AnswerKnowledgeQuestion::dispatchSync($this->priya,
        'Which days?', null, 'req-1');

    Event::assertDispatched(KnowledgeAnswerReady::class,
        fn (KnowledgeAnswerReady $e) => $e->requestId === 'req-1'
            && $e->broadcastOn()->name
                === "private-support.{$this->priya->id}"
            && $e->broadcastWith()['route'] === 'fallback');
});

it('shows an answer to its asker and to staff only', function () {
    KnowledgeAssistant::fake(['From memory.']);
    Sanctum::actingAs($this->priya);
    $id = $this->postJson('/api/knowledge/ask', [
        'question' => 'Which days?',
    ])->json('id');

    $this->getJson("/api/knowledge/answers/{$id}")->assertOk();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/knowledge/answers/{$id}")->assertForbidden();

    Sanctum::actingAs(User::factory()->create(['is_staff' => true]));
    $this->getJson("/api/knowledge/answers/{$id}")->assertOk();
});

it('takes feedback from the asker only', function () {
    $answer = KnowledgeAnswer::create([
        'user_id' => $this->priya->id, 'question' => 'Q?',
        'reply' => 'A.', 'citations' => [], 'route' => 'none',
    ]);

    Sanctum::actingAs(User::factory()->create(['is_staff' => true]));
    $this->postJson("/api/knowledge/answers/{$answer->id}/feedback",
        ['helpful' => false])->assertForbidden();

    Sanctum::actingAs($this->priya);
    $this->postJson("/api/knowledge/answers/{$answer->id}/feedback",
        ['helpful' => false, 'feedback' => 'It is in our terms.'])
        ->assertOk();

    expect($answer->fresh()->helpful)->toBeFalse();
});

it('exports unhelpful answers without personal data', function () {
    Storage::fake('local');
    KnowledgeAnswer::create([
        'user_id' => $this->priya->id,
        'question' => 'Email priya@brightlite.example the terms?',
        'reply' => 'Not covered.', 'citations' => [],
        'route' => 'none', 'helpful' => false,
        'feedback' => 'Call me on 98765 43210.',
    ]);

    $this->artisan('knowledge:feedback-export')->assertSuccessful();

    $cases = json_decode(Storage::disk('local')
        ->get('eval/knowledge-feedback.json'), true);

    expect($cases[0])
        ->question->toBe('Email [EMAIL_1] the terms?')
        ->feedback->toBe('Call me on [PHONE_1].')
        ->expect->toBeNull();
});

it('reports routes and ratings', function () {
    foreach (['agent', 'fallback', 'fallback', 'none'] as $route) {
        KnowledgeAnswer::create([
            'user_id' => $this->priya->id, 'question' => 'Q?',
            'reply' => 'A.', 'citations' => [], 'route' => $route,
            'helpful' => $route === 'fallback' ? true : null,
        ]);
    }

    $this->artisan('knowledge:report')
        ->expectsOutputToContain('4 answers; 2/2 of rated')
        ->assertSuccessful();
});
