<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Services\Rag\KnowledgeScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:scan')]
#[Description('Flag documents that contain instructions to a model')]
class ScanKnowledge extends Command
{
    public function handle(KnowledgeScanner $scanner): int
    {
        $this->info("Flagged {$scanner->scan()} document(s).");

        foreach (KnowledgeDocument::whereNotNull('flagged_at')->get()
            as $document) {
            $this->warn("  {$document->title}: {$document->flag_reason}");
        }

        return self::SUCCESS;
    }
}
