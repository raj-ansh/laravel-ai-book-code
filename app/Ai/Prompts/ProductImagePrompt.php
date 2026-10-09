<?php

namespace App\Ai\Prompts;

use App\Models\Product;
use InvalidArgumentException;

/**
 * Acme's house style for product images, in one place.
 */
class ProductImagePrompt
{
    private const STYLES = [
        'packshot' => 'Studio product photograph on a plain, '
            .'light grey background, soft even light, the whole '
            .'product in view, centred.',
        'lifestyle' => 'Photograph of the product in use in a '
            .'bright, modern Indian home, natural daylight, the '
            .'product clearly the subject.',
    ];

    public static function for(Product $product, string $style): string
    {
        $direction = self::STYLES[$style]
            ?? throw new InvalidArgumentException(
                "Unknown image style [{$style}]."
            );

        return "{$direction}\n\n"
            ."Product: {$product->name}. {$product->description}\n\n"
            .'Show only what the description says. No text, no '
            .'logos, no watermarks, no people\'s faces.';
    }
}
