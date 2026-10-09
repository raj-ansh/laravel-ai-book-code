<?php

use App\Models\KnowledgeDocument;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Rag\PolicyRetriever;
use App\Services\Rag\ScopedPolicyRetriever;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    // Every text gets the same vector: only access decides.
    Embeddings::fake(fn ($prompt) => array_map(
        fn () => array_pad([1.0], 768, 0.0), $prompt->inputs,
    ));

    $this->bright = Organisation::create(['name' => 'BrightLite']);
    $this->north = Organisation::create(['name' => 'Northwood']);

    foreach ([
        ['Returns policy', 'public', null, null],
        ['Supplier terms', 'staff', null, null],
        ['BrightLite contract', 'organisation', $this->bright->id, null],
        ['Northwood contract', 'organisation', $this->north->id, null],
        ['Poisoned FAQ', 'public', null, now()],
    ] as [$title, $audience, $organisation, $flagged]) {
        KnowledgeDocument::create([
            'title' => $title, 'source' => $title, 'hash' => $title,
            'audience' => $audience, 'organisation_id' => $organisation,
            'flagged_at' => $flagged,
        ])->chunks()->create([
            'position' => 0, 'content' => "{$title} text.",
            'embedding' => array_pad([1.0], 768, 0.0),
        ]);
    }
});

function visibleTitles(?User $user): array
{
    return (new ScopedPolicyRetriever($user))
        ->retrieve('anything', limit: 10)
        ->pluck('document.title')->sort()->values()->all();
}

it('shows guests only public, unflagged documents', function () {
    expect(visibleTitles(null))->toBe(['Returns policy']);
});

it('adds staff documents for staff', function () {
    expect(visibleTitles(User::factory()->staff()->create()))
        ->toBe(['Returns policy', 'Supplier terms']);
});

it('never shows one organisation another\'s documents', function () {
    $buyer = User::factory()->create([
        'organisation_id' => $this->bright->id,
    ]);

    expect(visibleTitles($buyer))
        ->toBe(['BrightLite contract', 'Returns policy']);
});

it('scopes every retrieval to the signed-in user', function () {
    $this->actingAs(User::factory()->staff()->create());

    expect(app(PolicyRetriever::class)->retrieve('anything', limit: 10)
        ->pluck('document.title'))->toContain('Supplier terms');
});
