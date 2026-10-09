<?php

namespace App\Console\Commands;

use App\Models\ContentDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\Content\ContentStudio;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:draft {sku} {--as=}')]
#[Description('Draft marketing content for a product and show it')]
class DraftContent extends Command
{
    public function handle(ContentStudio $studio): int
    {
        $product = Product::where('sku', $this->argument('sku'))
            ->firstOrFail();
        $user = User::where('email', $this->option('as'))->firstOrFail();

        $studio->draft($product, $user)->each(
            function (ContentDraft $draft) {
                $this->line("[{$draft->kind}] {$draft->body}");
                foreach ($draft->issues as $issue) {
                    $this->warn("  ! {$issue}");
                }
                $this->newLine();
            },
        );

        return self::SUCCESS;
    }
}
