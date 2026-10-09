<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'kind', 'body', 'issues', 'status',
    'requested_by', 'decided_by',
])]
class ContentDraft extends Model
{
    /** Where each kind is published on the product. */
    public const COLUMNS = [
        'description' => 'marketing_copy',
        'seo_title' => 'seo_title',
        'meta_description' => 'meta_description',
        'social_post' => null,     // posted by hand, not stored
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issues' => 'array'];
    }
}
