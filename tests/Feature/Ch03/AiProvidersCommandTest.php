<?php

use Illuminate\Support\Facades\Artisan;

it('reads capabilities from the provider classes', function () {
    Artisan::call('ai:providers');

    $output = Artisan::output();

    // Cohere embeds and reranks but cannot generate text.
    expect($output)->toMatch(
        '/\|\s*cohere\s*\|\s*-\s*\|\s*yes\s*\|\s*yes\s*\|/'
    )->and($output)->toMatch(
        '/\|\s*openai\s*\|\s*yes\s*\|/'
    );
});
