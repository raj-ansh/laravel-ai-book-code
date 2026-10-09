<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Chapter 46: delete AI trace spans older than two weeks.
Schedule::command('model:prune', [
    '--model' => [App\Models\AiSpan::class],
])->daily();
