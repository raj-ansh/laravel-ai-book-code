<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProductImage;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:image {sku} {--style=packshot}')]
#[Description('Queue an AI-generated image for a product')]
class GenerateProductImageCommand extends Command
{
    public function handle(): int
    {
        $product = Product::where('sku', $this->argument('sku'))
            ->firstOrFail();

        GenerateProductImage::dispatch(
            $product, $this->option('style'),
        );

        $this->info("Queued a {$this->option('style')} image "
            ."for {$product->name}.");

        return self::SUCCESS;
    }
}
