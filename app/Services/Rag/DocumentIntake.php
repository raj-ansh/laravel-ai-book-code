<?php

namespace App\Services\Rag;

use App\Jobs\IngestDocument;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Accepts a file into the knowledge base: store it once,
 * record it, queue its ingestion.
 */
class DocumentIntake
{
    public function accept(
        string $contents,
        string $title,
        string $extension,
        string $audience = 'staff',
        ?int $organisationId = null,
    ): KnowledgeDocument {
        $hash = sha1($contents);

        // The same file twice is one document.
        if ($existing = KnowledgeDocument::firstWhere('hash', $hash)) {
            return $existing;
        }

        $path = "knowledge/{$hash}.{$extension}";
        Storage::disk('local')->put($path, $contents);

        $document = KnowledgeDocument::create([
            'title' => $title,
            'source' => "upload:{$hash}",
            'hash' => $hash,
            'status' => 'pending',
            'path' => $path,
            'mime' => self::mimeFor($extension),
            // Chapter 35: who may retrieve it. Staff by default.
            'audience' => $audience,
            'organisation_id' => $organisationId,
        ]);

        IngestDocument::dispatch($document);

        return $document;
    }

    public static function mimeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'html', 'htm' => 'text/html',
            'md' => 'text/markdown',
            default => 'text/plain',
        };
    }
}
