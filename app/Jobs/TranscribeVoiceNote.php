<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\VoiceNote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Transcription;
use Throwable;

class TranscribeVoiceNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public int $timeout = 150;

    public function __construct(public VoiceNote $note) {}

    public function handle(): void
    {
        $transcript = Transcription::fromStorage(
            $this->note->path, 'local',
        )->language('en')->timeout(120)->generate();

        $text = trim($transcript->text);

        if ($text === '') {
            $this->note->update(['status' => 'empty']);

            return;
        }

        // A ticket like any other: Chapter 13 triages it.
        $ticket = Ticket::create([
            'user_id' => $this->note->user_id,
            'subject' => 'Voice note: '.Str::limit($text, 60),
            'body' => $text,
        ]);

        $this->note->update([
            'status' => 'transcribed',
            'transcript' => $text,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $this->note->update(['status' => 'failed']);
    }
}
