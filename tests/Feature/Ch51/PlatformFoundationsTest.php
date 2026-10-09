<?php

use App\Enums\AiFeature;
use App\Jobs\IngestDocument;
use App\Models\KnowledgeAnswer;
use App\Models\KnowledgeDocument;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Ai\AiAccess;
use Database\Seeders\KnowledgePlatformSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

function staffUser(): User
{
    return User::factory()->create(['is_staff' => true]);
}

/** An answer given to the user. */
function answerFor(User $user): KnowledgeAnswer
{
    return KnowledgeAnswer::create([
        'user_id' => $user->id, 'question' => 'Q?',
        'reply' => 'A.', 'citations' => [], 'route' => 'agent',
    ]);
}

it('seeds a document only Brightlite may read', function () {
    Queue::fake();

    $this->seed(KnowledgePlatformSeeder::class);

    $document = KnowledgeDocument::firstWhere(
        'title', 'Brightlite trade terms');

    expect($document)
        ->audience->toBe('organisation')
        ->organisation_id->toBe(Organisation::firstWhere(
            'name', 'Brightlite Retail')->id);
    Queue::assertPushed(IngestDocument::class);
});

it('lets the asker and staff read an answer', function () {
    $asker = User::factory()->create();
    $answer = answerFor($asker);

    expect(Gate::forUser($asker)->allows('view', $answer))->toBeTrue()
        ->and(Gate::forUser(staffUser())
            ->allows('view', $answer))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())
            ->allows('view', $answer))->toBeFalse();
});

it('lets only the asker rate an answer', function () {
    $asker = User::factory()->create();
    $answer = answerFor($asker);

    expect(Gate::forUser($asker)->allows('rate', $answer))->toBeTrue()
        ->and(Gate::forUser(staffUser())
            ->allows('rate', $answer))->toBeFalse();
});

it('includes knowledge search in the pro plan only', function () {
    $access = app(AiAccess::class);
    $member = fn (string $plan) => User::factory()->create([
        'organisation_id' => Organisation::create([
            'name' => $plan, 'ai_plan' => $plan])->id,
    ]);

    expect($access->allows($member('pro'), AiFeature::KnowledgeSearch))
        ->toBeTrue()
        ->and($access->allows($member('basic'),
            AiFeature::KnowledgeSearch))->toBeFalse()
        ->and($access->allows(User::factory()->create(),
            AiFeature::KnowledgeSearch))->toBeFalse();
});

it('casts citations and feedback', function () {
    $answer = answerFor(User::factory()->create());
    $answer->update([
        'citations' => [['document_id' => 1, 'title' => 'Shipping']],
        'helpful' => false,
    ]);

    expect($answer->fresh())
        ->citations->toBe([
            ['document_id' => 1, 'title' => 'Shipping'],
        ])
        ->helpful->toBeFalse();
});
