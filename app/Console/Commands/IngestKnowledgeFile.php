<?php

namespace App\Console\Commands;

use App\Services\Rag\DocumentIntake;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:ingest {path} {--title=}')]
#[Description('Add a local file to the knowledge base')]
class IngestKnowledgeFile extends Command
{
    public function handle(DocumentIntake $intake): int
    {
        $path = $this->argument('path');

        $document = $intake->accept(
            file_get_contents($path),
            $this->option('title') ?? basename($path),
            pathinfo($path, PATHINFO_EXTENSION),
        );

        $document->refresh();

        $this->line("{$document->title}: {$document->status}, "
            ."{$document->chunk_count} chunk(s)"
            .($document->error ? " ({$document->error})" : ''));

        return self::SUCCESS;
    }
}
