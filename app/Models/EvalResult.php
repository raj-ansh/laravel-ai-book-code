<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'eval_run_id', 'case_id', 'passed', 'score', 'output',
    'failed_checks',
])]
class EvalResult extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'output' => 'array',
            'failed_checks' => 'array',
        ];
    }
}
