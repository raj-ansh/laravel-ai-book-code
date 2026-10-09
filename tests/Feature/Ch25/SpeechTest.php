<?php

use App\Services\Support\SpokenReplies;
use App\Support\TranscriptFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Prompts\AudioPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\Data\TranscriptionUsage;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;

it('speaks a reply once and reuses the audio', function () {
    Storage::fake('public');
    Audio::fake();
    $reply = 'Your order A-1001 left our warehouse today.';

    $first = (new SpokenReplies)->urlFor($reply);
    $second = (new SpokenReplies)->urlFor($reply);

    expect($first)->toBe($second)->toEndWith('.mp3');
    Audio::assertGenerated(fn (AudioPrompt $prompt) =>
        $prompt->contains('A-1001') && $prompt->isFemale());
    expect(Storage::disk('public')->files('spoken'))->toHaveCount(1);
});

it('formats a diarised transcript with times', function () {
    Transcription::fake([new TranscriptionResponse(
        'Hello. Hi, about my order.',
        new Collection([
            new TranscriptionSegment('Hello.', 'Speaker 1', 0.4, 1.2),
            new TranscriptionSegment(
                ' Hi, about my order.', 'Speaker 2', 65.0, 67.5,
            ),
        ]),
        new TranscriptionUsage,
        new Meta('openai', 'whisper'),
    )]);

    $response = Transcription::fromPath('/tmp/call.wav')
        ->diarize()->generate();

    expect(TranscriptFormatter::lines($response))->toBe(
        "[0:00] Speaker 1: Hello.\n"
        .'[1:05] Speaker 2: Hi, about my order.'
    );
});
