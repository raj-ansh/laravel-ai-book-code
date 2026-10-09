<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Support\TicketTriageService;
use App\Services\Support\TriageResult;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\FailoverableException;
use Throwable;

class TriageTicket implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Attempts in all, including the first. */
    public int $tries = 3;

    /** Seconds to wait before the second and third attempts. */
    public array $backoff = [10, 60];

    /** Below retry_after (90 s), above the agent timeout (60 s). */
    public int $timeout = 75;

    public function __construct(public Ticket $ticket) {}

    /**
     * One pending triage per ticket, however often it is queued.
     */
    public function uniqueId(): string
    {
        return (string) $this->ticket->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ai')];
    }

    public function handle(TicketTriageService $triage): void
    {
        // Idempotent: a retry or a duplicate never triages twice.
        if ($this->ticket->triaged_at !== null) {
            return;
        }

        try {
            $triage->triage($this->ticket);
        } catch (FailoverableException $e) {
            throw $e; // temporary: let the queue retry it
        } catch (Throwable $e) {
            $this->fail($e); // permanent: retrying will not help
        }
    }

    /**
     * Every ticket ends up triaged, by AI or by the fallback.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Ticket triage failed', [
            'ticket_id' => $this->ticket->id,
            'exception' => $e ? $e::class : null,
        ]);

        $fallback = TriageResult::fallback();

        $this->ticket->update([
            'category' => $fallback->category,
            'priority' => $fallback->priority,
            'requires_human' => true,
            'summary' => $fallback->summary,
            'triaged_by' => $fallback->source,
            'triaged_at' => now(),
        ]);
    }
}
