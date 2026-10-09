<?php

use App\Jobs\IngestDocument;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Rag\DocumentIntake;
use App\Services\Rag\OverlappingChunker;
use App\Services\Rag\TextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->html = file_get_contents(
        resource_path('samples/docs/lamp-care.html')
    );
});

it('stores, records and queues a new document once', function () {
    Queue::fake();
    $intake = new DocumentIntake;

    $first = $intake->accept($this->html, 'Lamp care', 'html');
    $again = $intake->accept($this->html, 'Copy', 'html');

    expect($first->status)->toBe('pending')
        ->and($first->mime)->toBe('text/html')
        ->and($again->id)->toBe($first->id);
    Storage::disk('local')->assertExists($first->path);
    Queue::assertPushed(IngestDocument::class, 1);
});

it('lets only staff upload documents', function () {
    Queue::fake();
    $upload = fn () => [
        'title' => 'Care guide',
        'audience' => 'public',
        'file' => UploadedFile::fake()
            ->createWithContent('care.html', $this->html),
    ];

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/staff/knowledge/documents', $upload())
        ->assertForbidden();

    Sanctum::actingAs(User::factory()->staff()->create());
    $this->postJson('/api/staff/knowledge/documents', $upload())
        ->assertAccepted()
        ->assertJson(['title' => 'Care guide', 'status' => 'pending']);
});

it('marks a document without text as failed', function () {
    Storage::disk('local')->put('empty.txt', 'Too short.');
    $document = KnowledgeDocument::create([
        'title' => 'Empty', 'source' => 'upload:x', 'hash' => 'x',
        'status' => 'pending', 'path' => 'empty.txt',
        'mime' => 'text/plain',
    ]);
    $job = new IngestDocument($document);

    try {
        $job->handle(new TextExtractor, new OverlappingChunker);
    } catch (RuntimeException $e) {
        $job->failed($e);
    }

    expect($document->fresh())
        ->status->toBe('failed')
        ->error->toBe('No text could be extracted.');
});

it('extracts, chunks and embeds a document', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs PostgreSQL with pgvector.');
    }

    Queue::fake();
    // Unscripted fakes use the default provider's size; the
    // column holds 768 numbers.
    Embeddings::fake(fn ($prompt) => array_map(
        fn () => array_pad([1.0], 768, 0.0), $prompt->inputs,
    ));
    $document = (new DocumentIntake)
        ->accept($this->html, 'Lamp care', 'html');

    (new IngestDocument($document))->handle(
        new TextExtractor, new OverlappingChunker(maxChars: 200),
    );

    expect($document->fresh())
        ->status->toBe('ready')
        ->chunk_count->toBe(3)
        ->and($document->chunks()->first()->content)
        ->toStartWith('Lamp care guide');
});
