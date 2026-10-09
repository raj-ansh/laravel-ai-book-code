<?php

use App\Ai\Prompts\ProductImagePrompt;
use App\Jobs\GenerateProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\ImageResponse;

beforeEach(function () {
    Storage::fake('public');

    $this->lamp = Product::factory()->create([
        'sku' => 'SKU-1001',
        'name' => 'Brass desk lamp',
        'description' => 'Adjustable brass lamp, warm LED bulb.',
    ]);
});

it('builds prompts in the house style', function () {
    $prompt = ProductImagePrompt::for($this->lamp, 'packshot');

    expect($prompt)
        ->toContain('light grey background')
        ->toContain('Product: Brass desk lamp. Adjustable brass')
        ->toContain('No text, no logos');

    ProductImagePrompt::for($this->lamp, 'cartoon');
})->throws(InvalidArgumentException::class, 'Unknown image style');

it('generates, stores and holds an image for approval', function () {
    Image::fake();

    (new GenerateProductImage($this->lamp))->handle();

    $this->lamp->refresh();

    Storage::disk('public')->assertExists($this->lamp->image_path);
    expect($this->lamp->image_path)
        ->toStartWith('products/sku-1001-packshot-')
        ->and($this->lamp->publicImageUrl())->toBeNull();
    Image::assertGenerated(fn (ImagePrompt $prompt) =>
        $prompt->contains('Brass desk lamp')
        && $prompt->isLandscape()
        && $prompt->quality === 'medium');
});

it('shows only approved images to customers', function () {
    $this->lamp->update([
        'image_path' => 'products/lamp.png',
        'image_approved_at' => now(),
    ]);

    expect($this->lamp->publicImageUrl())
        ->toEndWith('/storage/products/lamp.png');
});

it('starts a lifestyle shot from the approved packshot', function () {
    Image::fake();
    Storage::disk('public')->put('products/lamp.png', 'png');
    $this->lamp->update([
        'image_path' => 'products/lamp.png',
        'image_approved_at' => now(),
    ]);

    (new GenerateProductImage($this->lamp, 'lifestyle'))->handle();

    Image::assertGenerated(fn (ImagePrompt $prompt) =>
        $prompt->contains('in use in a bright, modern Indian home')
        && $prompt->attachments[0] instanceof StoredImage);
});

it('queues the job from the command', function () {
    Queue::fake();

    $this->artisan('products:image', ['sku' => 'SKU-1001'])
        ->expectsOutput('Queued a packshot image for Brass desk lamp.')
        ->assertSuccessful();

    Queue::assertPushed(GenerateProductImage::class);
});

it('can queue a generation with the SDK alone', function () {
    Image::fake();

    Image::of('A brass desk lamp on a grey background')
        ->queue()
        ->then(fn (ImageResponse $image) => $image
            ->storePublicly('products', 'public'));

    Image::assertQueued(fn ($prompt) =>
        $prompt->contains('brass desk lamp'));
});
