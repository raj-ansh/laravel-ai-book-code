<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'organisation_id', 'feature', 'provider',
    'model', 'input_tokens', 'output_tokens', 'cost_micros',
    'invocation_id',
])]
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    // Rows are written once and never updated.
    public const UPDATED_AT = null;
}
