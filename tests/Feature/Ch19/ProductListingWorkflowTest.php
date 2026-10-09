<?php

use App\Ai\Agents\CopyReviewer;
use App\Ai\Agents\ProductCopywriter;
use App\Models\Product;
use App\Services\Catalog\ProductListingWorkflow;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    $this->lamp = Product::factory()->create([
        'name' => 'Brass desk lamp',
        'description' => 'Adjustable brass lamp, warm LED bulb.',
        'price_cents' => 459900,
    ]);
    $this->workflow = new ProductListingWorkflow;
});

it('publishes a draft the reviewer approves', function () {
    ProductCopywriter::fake(['A brass lamp with a warm LED.']);
    CopyReviewer::fake([['approved' => true, 'issues' => []]]);

    $draft = $this->workflow->run($this->lamp);

    expect($draft->approved())->toBeTrue()
        ->and($draft->revisions)->toBe(0);
    CopyReviewer::assertPrompted(fn (AgentPrompt $prompt) =>
        $prompt->contains('Price: INR 4,599.00')
        && $prompt->contains('A brass lamp with a warm LED.'));
});

it('fixes rule breaks without asking the reviewer', function () {
    ProductCopywriter::fake([
        'The best lamp ever!',
        'A brass lamp with a warm LED.',
    ]);
    CopyReviewer::fake([['approved' => true, 'issues' => []]]);

    $draft = $this->workflow->run($this->lamp);

    expect($draft->approved())->toBeTrue()
        ->and($draft->revisions)->toBe(1);
    ProductCopywriter::assertPrompted(fn (AgentPrompt $prompt) =>
        $prompt->contains('Remove the exclamation marks.'));
    CopyReviewer::assertPromptedTimes(1);
});

it('hands over to a person after one revision', function () {
    ProductCopywriter::fake([
        'A waterproof brass lamp.',
        'A brass lamp, also waterproof.',
    ]);
    CopyReviewer::fake([
        ['approved' => false, 'issues' => ['Not waterproof.']],
        ['approved' => false, 'issues' => ['Still waterproof.']],
    ]);

    $draft = $this->workflow->run($this->lamp);

    expect($draft->approved())->toBeFalse()
        ->and($draft->revisions)->toBe(1)
        ->and($draft->issues)->toBe(['Still waterproof.']);
});

it('treats an unreadable verdict as a rejection', function () {
    ProductCopywriter::fake(['A brass lamp.', 'A brass lamp.']);
    CopyReviewer::fake([[], ['approved' => false]]);

    expect($this->workflow->run($this->lamp)->issues)
        ->toBe(['The reviewer gave no usable verdict.']);
});
