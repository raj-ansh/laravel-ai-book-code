<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\Support\TicketTriageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:triage {ticket}')]
#[Description('Triage one support ticket with AI')]
class TicketsTriage extends Command
{
    public function handle(TicketTriageService $triage): int
    {
        $ticket = Ticket::findOrFail($this->argument('ticket'));

        $result = $triage->triage($ticket);

        $this->table(['field', 'value'], [
            ['category', $result->category->value],
            ['priority', $result->priority->value],
            ['requires_human', $result->requiresHuman ? 'yes' : 'no'],
            ['tags', implode(', ', $result->tags)],
            ['summary', $result->summary],
            ['source', $result->source],
        ]);

        return self::SUCCESS;
    }
}
