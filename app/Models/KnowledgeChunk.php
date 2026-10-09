<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'knowledge_document_id', 'position', 'content', 'embedding',
])]
class KnowledgeChunk extends Model
{
    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(
            KnowledgeDocument::class, 'knowledge_document_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['embedding' => AsVector::class];
    }
}
