<?php

use Illuminate\Support\Facades\Route;

use function Laravel\Ai\agent;

Route::get('/', function () {
    return view('welcome');
});

// Chapter 1: the smallest AI feature Laravel can have.
Route::get('/ai/hello', function () {
    $response = agent(
        instructions: 'You are the friendly assistant of Acme, '
            .'an online store. Keep answers short.',
    )->prompt('Greet a new customer in one sentence.');

    return $response->text;
});

// Chapter 12: a minimal page that reads the streamed chat.
Route::view('/support/chat', 'support-chat');
