<?php

namespace App\Services\Support;

use App\Ai\Agents\TicketTriage;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketTriageService
{
    public function triage(Ticket $ticket): TriageResult
    {
        $response = (new TicketTriage)->prompt(<<<TEXT
            <ticket>
            Subject: {$ticket->subject}

            {$ticket->body}
            </ticket>
            TEXT);

        try {
            $result = TriageResult::fromAi($response->toArray())
                ->withRules();
        } catch (ValidationException $e) {
            Log::warning('Ticket triage output rejected', [
                'ticket_id' => $ticket->id,
                'errors' => $e->errors(),
            ]);

            $result = TriageResult::fallback();
        }

        $ticket->update([
            'category' => $result->category,
            'priority' => $result->priority,
            'requires_human' => $result->requiresHuman,
            'tags' => $result->tags,
            'summary' => $result->summary,
            'triaged_by' => $result->source,
            'triaged_at' => now(),
        ]);

        return $result;
    }
}
