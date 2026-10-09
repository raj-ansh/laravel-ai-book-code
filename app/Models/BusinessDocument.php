<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'filename', 'path', 'mime', 'type', 'fields', 'issues', 'status',
])]
class BusinessDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fields' => 'array', 'issues' => 'array'];
    }
}
