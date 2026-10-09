<?php

use App\Ai\Agents\ContentWriter;
use App\Jobs\DraftProductContent;
use App\Models\ContentDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\Content\ContentChecks;
use App\Services\Content\ContentStudio;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->lamp = Product::factory()->create([
        'name' => 'Brass desk lamp',
        'description' => 'Adjustable brass lamp, warm LED bulb.',
        'price_cents' => 459900,
    ]);
    $this->meera = User::factory()->staff()->create();
});

it('flags copy that breaks the brand and fact rules', function () {
    $checks = new ContentChecks;

    expect($checks->issues('social_post',
        'The best ever lamp! Only $4,599.', $this->lamp))->toBe([
            'Uses the banned phrase "best ever".',
            'Uses exclamation marks.',
            'Mentions "$4,599": prices must be in INR.',
        ])
        ->and($checks->issues('meta_description',
            'A brass lamp for INR 3,999.', $this->lamp))
        ->toBe(['Mentions INR 3,999; the product costs INR 4,599.00.'])
        ->and($checks->issues('seo_title',
            str_repeat('Lamp ', 14).'| Acme', $this->lamp))
        ->toBe(['76 characters; the limit is 60.'])
        ->and($checks->issues('description',
            'A brass lamp, INR 4,599.', $this->lamp))->toBe([]);
});

it('drafts four kinds of content in one call', function () {
    ContentWriter::fake([[
        'description' => 'A warm brass lamp for your desk.',
        'seo_title' => 'Brass desk lamp | Acme',
        'meta_description' => 'Adjustable brass lamp, INR 4,599.',
        'social_post' => 'Meet the brass lamp! #Lamps',
    ]]);

    $drafts = app(ContentStudio::class)->draft($this->lamp, $this->meera);

    expect($drafts->pluck('kind')->all())->toBe([
        'description', 'seo_title', 'meta_description', 'social_post',
    ])
        ->and($drafts[3]->issues)->toBe(['Uses exclamation marks.'])
        ->and($drafts[0]->issues)->toBe([]);
    ContentWriter::assertPrompted(fn ($prompt) =>
        $prompt->contains('Price: INR 4,599.00'));
});

it('publishes approved copy beside the facts', function () {
    ContentWriter::fake([[
        'description' => 'A warm brass lamp for your desk.',
        'seo_title' => 'Brass desk lamp | Acme',
        'meta_description' => 'Adjustable brass lamp.',
        'social_post' => 'Meet the brass lamp. #Lamps',
    ]]);
    $studio = app(ContentStudio::class);
    $draft = $studio->draft($this->lamp, $this->meera)->first();

    $studio->approve($draft, $this->meera);

    expect($this->lamp->fresh())
        ->marketing_copy->toBe('A warm brass lamp for your desk.')
        ->description->toBe('Adjustable brass lamp, warm LED bulb.');

    $studio->approve($draft->fresh(), $this->meera);
})->throws(InvalidArgumentException::class, 'already approved');

it('lets only staff use the studio', function () {
    Queue::fake();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/staff/content/products/{$this->lamp->id}/drafts")
        ->assertForbidden();

    Sanctum::actingAs($this->meera);
    $this->postJson("/api/staff/content/products/{$this->lamp->id}/drafts")
        ->assertAccepted();
    Queue::assertPushed(DraftProductContent::class);
});

it('approves and rejects drafts over the API', function () {
    Sanctum::actingAs($this->meera);
    $draft = ContentDraft::create([
        'product_id' => $this->lamp->id, 'kind' => 'seo_title',
        'body' => 'Brass desk lamp | Acme', 'issues' => [],
        'requested_by' => $this->meera->id,
    ]);

    $this->postJson("/api/staff/content/drafts/{$draft->id}/approve")
        ->assertOk();
    $this->postJson("/api/staff/content/drafts/{$draft->id}/approve")
        ->assertConflict();

    expect($this->lamp->fresh()->seo_title)
        ->toBe('Brass desk lamp | Acme');
});
