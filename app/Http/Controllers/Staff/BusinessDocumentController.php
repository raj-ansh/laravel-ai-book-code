<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBusinessDocument;
use App\Models\BusinessDocument;
use App\Services\Rag\DocumentIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BusinessDocumentController extends Controller
{
    /** Upload a document; it is processed in the background. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-knowledge');

        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:pdf,txt,html,htm',
            ],
        ]);

        $file = $request->file('file');
        $document = BusinessDocument::create([
            'filename' => $file->getClientOriginalName(),
            'path' => $file->store('business-documents', 'local'),
            'mime' => DocumentIntake::mimeFor(
                $file->getClientOriginalExtension()
            ),
            'status' => 'pending',
        ]);

        ProcessBusinessDocument::dispatch($document);

        return response()->json($document->only(['id', 'status']), 202);
    }

    /** The review queue: documents a person must look at. */
    public function index(): JsonResponse
    {
        Gate::authorize('manage-knowledge');

        return response()->json(BusinessDocument::query()
            ->where('status', 'needs_review')
            ->latest()
            ->get(['id', 'filename', 'type', 'fields', 'issues']));
    }

    public function show(BusinessDocument $document): JsonResponse
    {
        Gate::authorize('manage-knowledge');

        return response()->json($document->only([
            'id', 'filename', 'type', 'fields', 'issues', 'status',
        ]));
    }
}
