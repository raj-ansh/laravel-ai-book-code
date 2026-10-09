<?php

namespace App\Jobs;

use App\Ai\Prompts\ProductImagePrompt;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;

class GenerateProductImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public Product $product,
        public string $style = 'packshot',
    ) {}

    public function uniqueId(): string
    {
        return "{$this->product->id}-{$this->style}";
    }

    public function handle(): void
    {
        $request = Image::of(
            ProductImagePrompt::for($this->product, $this->style)
        )->landscape()->quality('medium')->timeout(90);

        // A lifestyle shot starts from the approved packshot,
        // so the product looks the same in both.
        if ($this->style === 'lifestyle'
            && $this->product->publicImageUrl()) {
            $request->attachments([ImageFile::fromStorage(
                $this->product->image_path, 'public',
            )]);
        }

        $path = $request->generate()->storePubliclyAs(
            'products',
            Str::slug($this->product->sku)."-{$this->style}-"
                .Str::random(6).'.png',
            'public',
        );

        // A new image always waits for a person to approve it.
        $this->product->update([
            'image_path' => $path,
            'image_approved_at' => null,
        ]);
    }
}
