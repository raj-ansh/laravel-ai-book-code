<?php

use App\Jobs\TranscribeVoiceNote;
use App\Jobs\TriageTicket;
use App\Models\User;
use App\Models\VoiceNote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->asha = User::factory()->create();
});

it('accepts a voice note and queues it', function () {
    Sanctum::actingAs($this->asha);

    $this->postJson('/api/support/voice-notes', [
        'audio' => UploadedFile::fake()
            ->create('note.m4a', 300, 'audio/mp4'),
    ])->assertAccepted()->assertJson(['status' => 'pending']);

    $note = VoiceNote::sole();
    Storage::disk('local')->assertExists($note->path);
    Queue::assertPushed(TranscribeVoiceNote::class);
});

it('rejects files that are not audio', function () {
    Sanctum::actingAs($this->asha);

    $this->postJson('/api/support/voice-notes', [
        'audio' => UploadedFile::fake()->create('notes.pdf', 10),
    ])->assertUnprocessable()->assertJsonValidationErrors('audio');
});

it('turns a transcript into a ticket for triage', function () {
    Transcription::fake([
        'Hi, my order A-1001 arrived with a cracked base.',
    ]);
    $note = VoiceNote::create([
        'user_id' => $this->asha->id, 'path' => 'voice/a.m4a',
    ]);

    (new TranscribeVoiceNote($note))->handle();

    expect($note->fresh())
        ->status->toBe('transcribed')
        ->ticket->body->toBe(
            'Hi, my order A-1001 arrived with a cracked base.'
        );
    Transcription::assertGenerated(
        fn (TranscriptionPrompt $prompt) =>
            $prompt->language === 'en' && ! $prompt->isDiarized()
    );
    Queue::assertPushed(TriageTicket::class);
});

it('creates no ticket from silence', function () {
    Transcription::fake(['  ']);
    $note = VoiceNote::create([
        'user_id' => $this->asha->id, 'path' => 'voice/b.m4a',
    ]);

    (new TranscribeVoiceNote($note))->handle();

    expect($note->fresh())
        ->status->toBe('empty')
        ->ticket_id->toBeNull();
});
