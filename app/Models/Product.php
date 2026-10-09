<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'sku', 'name', 'description', 'price_cents', 'stock',
    'image_path', 'image_approved_at',
    'embedding', 'embedding_model', 'embedding_hash',
    'marketing_copy', 'seo_title', 'meta_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The image customers may see: only an approved one.
     */
    public function publicImageUrl(): ?string
    {
        return $this->image_path && $this->image_approved_at
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    /**
     * What the embedding represents (Chapter 28).
     */
    public function embeddingText(): string
    {
        return "{$this->name}. {$this->description}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'image_approved_at' => 'datetime',
            'embedding' => AsVector::class,
        ];
    }
}
