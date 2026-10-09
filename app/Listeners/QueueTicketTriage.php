<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\TriageTicket;

class QueueTicketTriage
{
    public function handle(TicketCreated $event): void
    {
        // Wait for the transaction that created the ticket, so
        // the worker never looks for a row that is not there.
        TriageTicket::dispatch($event->ticket)->afterCommit();
    }
}
