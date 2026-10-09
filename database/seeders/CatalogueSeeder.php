<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    /**
     * More products, for the search chapters (Part V).
     */
    public function run(): void
    {
        foreach ([
            ['SKU-2001', 'Rattan pendant light', 'Hand-woven rattan ceiling shade, 45 cm, for a warm, natural look.', 349900, 12],
            ['SKU-2002', 'Clip-on reading light', 'Small battery LED lamp that clips onto a book or headboard.', 89900, 40],
            ['SKU-2003', 'Smart bulb, 9W', 'Wi-Fi LED bulb, dimmable, 16 million colours, works with voice assistants.', 129900, 60],
            ['SKU-2004', 'Teak coffee table', 'Low table in solid teak with a lower shelf for magazines.', 1499900, 5],
            ['SKU-2005', 'Folding study desk', 'Compact desk that folds flat against the wall, for small flats.', 649900, 9],
            ['SKU-2006', 'Cotton cushion covers', 'Set of two block-printed cotton covers, 45 x 45 cm, indigo.', 119900, 30],
            ['SKU-2007', 'Blackout curtains', 'Thick curtains that block light and keep rooms cool in summer.', 279900, 14],
            ['SKU-2008', 'Bamboo shoe rack', 'Four-tier rack for up to 12 pairs, water-resistant finish.', 229900, 0],
            ['SKU-2009', 'Kids night light', 'Soft-glow silicone owl lamp, rechargeable, safe to touch.', 99900, 25],
            ['SKU-2010', 'Standing desk frame', 'Electric height-adjustable desk frame, two motors, memory presets.', 2899900, 4],
            ['SKU-2011', 'Wall-mounted shelf', 'Floating oak shelf, 80 cm, hidden brackets, holds 15 kg.', 199900, 22],
            ['SKU-2012', 'Desk organiser', 'Wooden tray with slots for pens, phone and notebooks.', 79900, 35],
            ['SKU-2013', 'Solar garden lights', 'Set of six stake lights that charge by day and glow at night.', 159900, 18],
            ['SKU-2014', 'Ceramic table lamp', 'Hand-glazed ceramic base with a linen shade, warm white light.', 399900, 7],
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
