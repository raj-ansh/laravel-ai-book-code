<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntakeCase;
use App\Models\IntakeCase;
use App\Services\Intake\IntakeEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            // Checked by content: a forwarded email saved as .txt
            // is sniffed as message/rfc822, so "eml" is listed.
            'attachment' => ['nullable', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,txt,eml,html,'
                .'mp3,m4a,wav,ogg,webm'],
        ]);

        $file = $request->file('attachment');
        $mime = $file?->getMimeType();

        $case = IntakeCase::create([
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
            'attachment_path' => $file?->store('intake', 'local'),
            'attachment_mime' => $mime,
            'attachment_kind' => $mime
                ? IntakeEvidence::kindOf($mime) : null,
            'status' => 'pending',
        ]);

        ProcessIntakeCase::dispatch($case);

        return response()->json(['id' => $case->id], 202);
    }

    public function show(
        Request $request,
        IntakeCase $case,
    ): JsonResponse {
        abort_unless($case->user_id === $request->user()->id, 404);

        // The customer sees the status, not the internal issues.
        return response()->json($case->only([
            'id', 'status', 'category', 'attachment_kind',
        ]));
    }
}
