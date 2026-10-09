<?php

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;

/*
 * The shape of the application, enforced. Each rule is one
 * the book relies on; a change that breaks one should be a
 * decision, made in review, not an accident.
 *
 * One target and one dependency per rule. Written with
 * lists (expect([A, B])->not->toUse([X, Y])), these rules
 * passed while the code broke them; the loops below do not.
 */

arch('agents are agents')
    ->expect('App\Ai\Agents')
    ->toImplement(Agent::class);

arch('tools are tools')
    ->expect('App\Ai\Tools')
    ->classes() // QuoteItems is a trait the quote tools share
    ->toImplement(Tool::class);

arch('agents never query the database themselves')
    ->expect('App\Ai\Agents')
    ->not->toUse('Illuminate\Support\Facades\DB');

foreach (['App\Ai', 'App\Services'] as $layer) {
    foreach (['Illuminate\Http\Request', 'App\Http'] as $http) {
        arch("{$layer} does not use {$http}")
            ->expect($layer)
            ->not->toUse($http);
    }
}

arch('evaluation is never used by the application')
    ->expect('App\Evaluation')
    ->toOnlyBeUsedIn(['App\Evaluation', 'App\Console']);

arch('configuration is read through config()')
    ->expect('App')
    ->not->toUse('env');

foreach (['dd', 'dump', 'ray', 'var_dump'] as $function) {
    arch("no {$function}() left in the code")
        ->expect($function)
        ->not->toBeUsed();
}
