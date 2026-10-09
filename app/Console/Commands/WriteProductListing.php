<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductListingWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:listing {sku}')]
#[Description('Write and review a product description')]
class WriteProductListing extends Command
{
    public function handle(ProductListingWorkflow $workflow): int
    {
        $product = Product::where('sku', $this->argument('sku'))
            ->firstOrFail();

        $draft = $workflow->run($product);

        $this->line($draft->text);
        $this->newLine();
        $this->line("Revisions: {$draft->revisions}");

        if (! $draft->approved()) {
            $this->warn('Needs a person: '
                .implode(' ', $draft->issues));

            return self::FAILURE;
        }

        $this->info('Approved.');

        return self::SUCCESS;
    }
}
