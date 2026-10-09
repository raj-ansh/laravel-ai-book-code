<?php

use App\Ai\Agents\HandbookSearchAgent;
use App\Models\KbDocument;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Stores;

beforeEach(function () {
    Storage::fake('kb');
    Storage::disk('kb')->put('policies/returns.md', 'Returns: 30 days.');
    Storage::disk('kb')->put('policies/shipping.md', 'Ships in 3 days.');

    Stores::fake();
    Files::fake();
    config(['acme.ai.handbook_store.id' => 'vs_handbook']);
});

it('uploads every policy once, with metadata', function () {
    $this->artisan('kb:sync')
        ->expectsOutputToContain('2 added, 0 replaced, 0 removed')
        ->assertSuccessful();

    expect(KbDocument::count())->toBe(2);
    Stores::get('vs_handbook')->assertAdded(fn ($file) => true);

    $this->artisan('kb:sync')
        ->expectsOutputToContain('0 added, 0 replaced, 0 removed');
});

it('replaces changed files and removes deleted ones', function () {
    $this->artisan('kb:sync');

    Storage::disk('kb')->put('policies/returns.md', 'Returns: 45 days.');
    Storage::disk('kb')->delete('policies/shipping.md');

    $this->artisan('kb:sync')
        ->expectsOutputToContain('0 added, 1 replaced, 1 removed');

    expect(KbDocument::pluck('path')->all())
        ->toBe(['policies/returns.md']);
});

it('creates a store when none is configured', function () {
    config(['acme.ai.handbook_store.id' => null]);

    $this->artisan('kb:sync')
        ->expectsOutputToContain('New store. Set ACME_HANDBOOK_STORE_ID=')
        ->assertSuccessful();

    Stores::assertCreated('Acme handbook');
});

it('searches only the customer documents in the store', function () {
    $tool = (new HandbookSearchAgent)->tools()[0];

    expect($tool)->toBeInstanceOf(FileSearch::class)
        ->and($tool->ids())->toBe(['vs_handbook'])
        ->and($tool->filters)->toBe([[
            'type' => 'eq', 'key' => 'audience', 'value' => 'customers',
        ]]);
});
