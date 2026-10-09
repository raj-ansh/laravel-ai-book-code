<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'trace_id', 'span_id', 'parent_id', 'kind', 'name', 'status',
    'duration_ms', 'input_tokens', 'output_tokens', 'error',
])]
class AiSpan extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Traces are for debugging recent behaviour: two weeks.
     * Run by `php artisan model:prune` on the schedule.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(14));
    }
}
