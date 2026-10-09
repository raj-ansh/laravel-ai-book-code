<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Jobs\TranscribeVoiceNote;
use App\Models\VoiceNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceNoteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            // 10 MB is a few minutes of compressed speech.
            'audio' => [
                'required', 'file', 'max:10240',
                'mimes:mp3,m4a,wav,ogg,webm',
            ],
        ]);

        $note = VoiceNote::create([
            'user_id' => $request->user()->id,
            'path' => $request->file('audio')->store(
                "voice-notes/{$request->user()->id}", 'local',
            ),
            'status' => 'pending',
        ]);

        TranscribeVoiceNote::dispatch($note);

        return response()->json([
            'id' => $note->id,
            'status' => $note->status,
        ], 202);
    }
}
