<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Reviews of the brass desk lamp, for classification.
     */
    public function run(): void
    {
        $lamp = Product::where('sku', 'SKU-1001')->firstOrFail();

        foreach ([
            [5, 'Beautiful lamp, solid brass, the warm light is '
                .'perfect for reading.'],
            [2, 'The lamp is fine but it arrived ten days late and '
                .'the box was crushed.'],
            [1, 'The switch stopped working after a week. I want '
                .'my money back, nobody answers my emails.'],
            [3, 'Nice enough, but INR 4,599 is a lot for a desk '
                .'lamp.'],
            [4, 'Good lamp. The support agent sorted out a billing '
                .'mistake quickly and politely.'],
            [5, 'Exactly as pictured.'],
        ] as [$rating, $body]) {
            Review::create([
                'product_id' => $lamp->id,
                'rating' => $rating,
                'body' => $body,
            ]);
        }
    }
}
