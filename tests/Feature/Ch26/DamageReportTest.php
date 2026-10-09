<?php

use App\Ai\Agents\DamagePhotoInspector;
use App\Jobs\InspectDamagePhoto;
use App\Models\DamageReport;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');

    $this->asha = User::factory()->create();
    $this->order = Order::factory()->for($this->asha)
        ->create(['number' => 'A-1001']);
});

it('accepts a photo for the customer\'s own order', function () {
    Queue::fake();
    Sanctum::actingAs($this->asha);

    $this->postJson("/api/orders/{$this->order->id}/damage-reports", [
        'photo' => UploadedFile::fake()->image('lamp.jpg', 800, 600),
    ])->assertAccepted();

    Storage::disk('local')
        ->assertExists(DamageReport::sole()->photo_path);
    Queue::assertPushed(InspectDamagePhoto::class);
});

it('refuses photos for other people\'s orders', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/orders/{$this->order->id}/damage-reports", [
        'photo' => UploadedFile::fake()->image('lamp.jpg', 800, 600),
    ])->assertForbidden();
});

it('refuses files that are not usable photos', function (
    UploadedFile $file,
) {
    Sanctum::actingAs($this->asha);

    $this->postJson("/api/orders/{$this->order->id}/damage-reports", [
        'photo' => $file,
    ])->assertUnprocessable();
})->with([
    'not an image' => fn () => UploadedFile::fake()
        ->create('lamp.pdf', 100, 'application/pdf'),
    'too small' => fn () => UploadedFile::fake()
        ->image('lamp.jpg', 100, 100),
]);

it('stores what the vision model sees', function () {
    DamagePhotoInspector::fake([[
        'description' => 'A lamp base with a crack across it.',
        'damage_visible' => true,
        'damage_type' => 'cracked',
    ]]);
    $report = DamageReport::create([
        'order_id' => $this->order->id,
        'photo_path' => 'damage/1/lamp.jpg',
    ]);

    (new InspectDamagePhoto($report))->handle();

    expect($report->fresh())
        ->status->toBe('inspected')
        ->damage_visible->toBeTrue()
        ->damage_type->toBe('cracked');
    DamagePhotoInspector::assertPrompted(fn (AgentPrompt $prompt) =>
        $prompt->contains('Order A-1001')
        && $prompt->attachments->first() instanceof StoredImage);
});

it('never records damage the model did not report', function () {
    DamagePhotoInspector::fake([[
        'description' => 'A lamp.', 'damage_type' => 'shattered',
    ]]);
    $report = DamageReport::create([
        'order_id' => $this->order->id,
        'photo_path' => 'damage/1/lamp.jpg',
    ]);

    (new InspectDamagePhoto($report))->handle();

    expect($report->fresh())
        ->damage_visible->toBeFalse()
        ->damage_type->toBe('other');
});
