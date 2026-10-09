<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeDocument;
use App\Services\Rag\DocumentIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KnowledgeDocumentController extends Controller
{
    public function store(
        Request $request,
        DocumentIntake $intake,
    ): JsonResponse {
        Gate::authorize('manage-knowledge');

        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:pdf,html,htm,md,txt',
            ],
            'title' => ['required', 'string', 'max:200'],
            'audience' => ['required', 'in:public,staff,organisation'],
            'organisation_id' => [
                'required_if:audience,organisation',
                'nullable', 'exists:organisations,id',
            ],
        ]);

        $file = $request->file('file');

        $document = $intake->accept(
            $file->get(),
            $request->string('title'),
            $file->getClientOriginalExtension(),
            $request->string('audience'),
            $request->integer('organisation_id') ?: null,
        );

        return response()->json(
            $document->only(['id', 'title', 'status']),
            $document->wasRecentlyCreated ? 202 : 200,
        );
    }

    public function show(KnowledgeDocument $document): JsonResponse
    {
        Gate::authorize('manage-knowledge');

        return response()->json($document->only([
            'id', 'title', 'status', 'chunk_count', 'error',
        ]));
    }
}
