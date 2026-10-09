<?php

use App\Ai\Agents\TicketTriage;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Services\Support\TicketTriageService;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

$triage = [
    'reason' => 'Login problem.',
    'category' => 'account',
    'priority' => 'high',
    'requires_human' => false,
    'tags' => ['login'],
    'summary' => 'Cannot log in.',
    'customer_name' => null,
];

it('queues triage when a ticket is created', function () {
    Queue::fake();

    $ticket = Ticket::factory()->create();

    Queue::assertPushed(
        TriageTicket::class,
        fn (TriageTicket $job) => $job->ticket->is($ticket)
    );
});

it('triages the ticket when the job runs', function () use ($triage) {
    TicketTriage::fake([$triage]);
    $ticket = Ticket::factory()->createQuietly();

    (new TriageTicket($ticket))
        ->handle(app(TicketTriageService::class));

    expect($ticket->fresh()->summary)->toBe('Cannot log in.');
});

it('never triages the same ticket twice', function () {
    TicketTriage::fake();
    $ticket = Ticket::factory()->createQuietly([
        'triaged_at' => now(),
    ]);

    (new TriageTicket($ticket))
        ->handle(app(TicketTriageService::class));

    TicketTriage::assertNeverPrompted();
});

it('lets the queue retry a temporary failure', function () {
    TicketTriage::fake(function () {
        throw ProviderOverloadedException::forProvider('openai');
    });

    $job = new TriageTicket(Ticket::factory()->createQuietly());

    $job->handle(app(TicketTriageService::class));
})->throws(ProviderOverloadedException::class);

it('fails at once on a permanent error', function () {
    TicketTriage::fake(function () {
        throw new RuntimeException('Invalid API key.');
    });

    $job = (new TriageTicket(Ticket::factory()->createQuietly()))
        ->withFakeQueueInteractions();

    $job->handle(app(TicketTriageService::class));

    $job->assertFailed();
});

it('falls back to human triage when it gives up', function () {
    $ticket = Ticket::factory()->createQuietly();

    (new TriageTicket($ticket))->failed(new RuntimeException);

    expect($ticket->fresh())
        ->triaged_by->toBe('fallback')
        ->requires_human->toBeTrue()
        ->triaged_at->not->toBeNull();
});

it('is unique per ticket and rate limited', function () {
    $ticket = Ticket::factory()->createQuietly();
    $job = new TriageTicket($ticket);

    expect($job->uniqueId())->toBe((string) $ticket->id)
        ->and($job->middleware()[0])
        ->toBeInstanceOf(RateLimited::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60]);
});
