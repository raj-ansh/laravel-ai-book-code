<?php

use App\Ai\Agents\TicketTriage;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Jobs\InvokeAgent;

it('queues a prompt with the SDK in one call', function () {
    Queue::fake();
    TicketTriage::fake();

    (new TicketTriage)->queue('I cannot log in.');

    TicketTriage::assertQueued('I cannot log in.');
    TicketTriage::assertNeverPrompted();

    Queue::assertPushed(InvokeAgent::class);
});
