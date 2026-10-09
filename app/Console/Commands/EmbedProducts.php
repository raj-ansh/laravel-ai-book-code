<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductEmbedder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:embed')]
#[Description('Embed products whose text has changed')]
class EmbedProducts extends Command
{
    public function handle(ProductEmbedder $embedder): int
    {
        $this->info("Embedded {$embedder->embedStale()} product(s).");

        return self::SUCCESS;
    }
}
