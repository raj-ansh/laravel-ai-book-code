<?php

namespace App\Console\Commands;

use App\Services\Rag\HandbookIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('policies:index')]
#[Description('Chunk and embed the policy handbook')]
class IndexPolicies extends Command
{
    public function handle(HandbookIndexer $indexer): int
    {
        $this->info("Indexed {$indexer->index()} document(s).");

        return self::SUCCESS;
    }
}
