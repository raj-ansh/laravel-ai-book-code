<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Services\Content\ContentStudio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DraftProductContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public Product $product,
        public User $requestedBy,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->product->id;
    }

    public function handle(ContentStudio $studio): void
    {
        $studio->draft($this->product, $this->requestedBy);
    }
}
