<?php

namespace App\Console\Commands;

use App\Models\BusinessDocument;
use App\Services\Documents\DocumentProcessor;
use App\Services\Rag\DocumentIntake;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('docs:process {path}')]
#[Description('Classify a business document and extract its fields')]
class ProcessDocument extends Command
{
    public function handle(DocumentProcessor $processor): int
    {
        $path = $this->argument('path');
        $stored = 'business-documents/'.basename($path);
        Storage::disk('local')->put($stored, file_get_contents($path));

        $document = $processor->process(BusinessDocument::create([
            'filename' => basename($path),
            'path' => $stored,
            'mime' => DocumentIntake::mimeFor(
                pathinfo($path, PATHINFO_EXTENSION)
            ),
            'status' => 'pending',
        ]));

        $this->line("{$document->filename}: {$document->type}, "
            ."{$document->status}");
        $this->line('  '.json_encode($document->fields));
        foreach ($document->issues as $issue) {
            $this->warn("  ! {$issue}");
        }

        return self::SUCCESS;
    }
}
