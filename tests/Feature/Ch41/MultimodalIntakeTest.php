<?php

use App\Ai\Agents\DamagePhotoInspector;
use App\Ai\Agents\IntakeCaseExtractor;
use App\Jobs\ProcessIntakeCase;
use App\Models\IntakeCase;
use App\Models\Order;
use App\Models\User;
use App\Services\Intake\IntakeEvidence;
use App\Services\Intake\IntakeProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Transcription;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->customer = User::factory()->create();
    $this->order = Order::factory()->for($this->customer)
        ->create(['number' => 'A-1001']);
});

/** A stored case with an optional attachment. */
function intakeCase(
    User $user,
    string $message,
    ?string $content = null,
    string $mime = 'text/plain',
): IntakeCase {
    $path = null;

    if ($content !== null) {
        $path = 'intake/'.Str::random(8);
        Storage::disk('local')->put($path, $content);
    }

    return IntakeCase::create([
        'user_id' => $user->id, 'message' => $message,
        'attachment_path' => $path,
        'attachment_mime' => $content !== null ? $mime : null,
        'attachment_kind' => $content !== null
            ? IntakeEvidence::kindOf($mime) : null,
        'status' => 'pending',
    ]);
}

/** A scripted case extraction. */
function extracted(
    string $category,
    string $number,
    string $wants,
): array {
    return ['category' => $category, 'order_number' => $number,
        'wants' => $wants, 'summary' => 'A case.'];
}

it('opens a case from a text message', function () {
    IntakeCaseExtractor::fake([
        extracted('not_delivered', 'a-1001', 'information'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, 'Order A-1001 has not arrived.',
    ));

    expect($case)
        ->status->toBe('open')
        ->order_id->toBe($this->order->id)
        ->issues->toBe([]);
});

it('reads the order number from an attached document', function () {
    IntakeCaseExtractor::fake([
        extracted('refund_request', 'A-1001', 'refund'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, 'Refund please, email attached.',
        "From: orders@acme.example\nYour order A-1001 has shipped.",
        'message/rfc822',
    ));

    expect($case)
        ->attachment_kind->toBe('document')
        ->evidence->toContain('A-1001')
        ->status->toBe('open');
});

it('questions a damage claim the photo does not support', function () {
    DamagePhotoInspector::fake([[
        'description' => 'A white mug on a table.',
        'damage_visible' => false, 'damage_type' => 'none',
    ]]);
    IntakeCaseExtractor::fake([
        extracted('damaged_item', 'A-1001', 'refund'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, 'My mug from A-1001 arrived cracked.',
        UploadedFile::fake()->image('mug.jpg')->getContent(),
        'image/jpeg',
    ));

    expect($case)
        ->status->toBe('needs_review')
        ->issues->toBe(['The photo shows no visible damage.'])
        ->evidence->toBe('A white mug on a table.');
});

it('transcribes a voice note', function () {
    Transcription::fake(['Order A-1001 never came.']);
    IntakeCaseExtractor::fake([
        extracted('not_delivered', 'A-1001', 'information'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, '(voice note)', 'audio-bytes', 'audio/mpeg',
    ));

    expect($case)
        ->attachment_kind->toBe('voice_note')
        ->evidence->toBe('Order A-1001 never came.')
        ->status->toBe('open');
});

it('rejects order numbers that are invented or not theirs', function (
    string $number,
) {
    Order::factory()->create(['number' => 'A-2001']);
    IntakeCaseExtractor::fake([
        extracted('refund_request', $number, 'refund'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, 'Please refund order A-2001.',
    ));

    expect($case)
        ->status->toBe('needs_review')
        ->order_id->toBeNull()
        ->issues->toBe(["Order {$number} is not one of this "
            ."customer's orders."]);
})->with([
    'another customer\'s order' => 'A-2001',
    'not in the message' => 'A-1234',
]);

it('asks for the order when none is given', function () {
    IntakeCaseExtractor::fake([
        extracted('damaged_item', '', 'replacement'),
    ]);

    $case = app(IntakeProcessor::class)->process(intakeCase(
        $this->customer, 'My plate arrived broken.',
    ));

    expect($case->issues)
        ->toBe(['No order number: ask the customer which order.']);
});

it('accepts uploads through the API', function () {
    Queue::fake();
    Sanctum::actingAs($this->customer);

    // A real file, so the type is sniffed from its content.
    $email = new UploadedFile(
        resource_path('samples/intake/order-email.txt'),
        'order-email.txt', null, null, true,
    );

    $id = $this->postJson('/api/support/intake', [
        'message' => 'Refund please.', 'attachment' => $email,
    ])->assertAccepted()->json('id');

    Queue::assertPushed(ProcessIntakeCase::class);
    expect(IntakeCase::find($id)->attachment_kind)->toBe('document');

    $this->getJson("/api/support/intake/{$id}")->assertOk()
        ->assertJsonMissingPath('issues');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/support/intake/{$id}")->assertNotFound();
});
