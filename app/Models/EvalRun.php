<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'suite', 'model', 'prompt_hash', 'cases', 'passed', 'score',
    'duration_ms',
])]
class EvalRun extends Model
{
    /**
     * @return HasMany<EvalResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvalResult::class);
    }
}
