<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * A small catalogue for the sales chapters.
     */
    public function run(): void
    {
        foreach ([
            ['SKU-1001', 'Brass desk lamp', 'Adjustable brass desk lamp with a warm LED bulb.', 459900, 25],
            ['SKU-1002', 'LED floor lamp', 'Dimmable floor lamp, 160 cm, matt black.', 799900, 8],
            ['SKU-1003', 'Walnut bookshelf', 'Five-shelf bookshelf in solid walnut.', 1850000, 3],
            ['SKU-1004', 'Ergonomic office chair', 'Mesh office chair with lumbar support.', 1299900, 0],
        ] as [$sku, $name, $description, $price, $stock]) {
            Product::create([
                'sku' => $sku,
                'name' => $name,
                'description' => $description,
                'price_cents' => $price,
                'stock' => $stock,
            ]);
        }
    }
}
