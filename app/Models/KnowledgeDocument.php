<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'source', 'hash',
    'status', 'path', 'mime', 'chunk_count', 'error',
    'audience', 'organisation_id', 'flagged_at', 'flag_reason',
])]
class KnowledgeDocument extends Model
{
    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
