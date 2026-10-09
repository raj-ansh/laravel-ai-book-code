<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'message', 'attachment_path', 'attachment_mime',
    'attachment_kind', 'evidence', 'category', 'order_id',
    'fields', 'issues', 'status',
])]
class IntakeCase extends Model
{
    protected function casts(): array
    {
        return ['fields' => 'array', 'issues' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
