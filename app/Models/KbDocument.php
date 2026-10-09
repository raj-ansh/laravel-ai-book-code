<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['path', 'hash', 'file_id', 'document_id'])]
class KbDocument extends Model
{
    //
}
