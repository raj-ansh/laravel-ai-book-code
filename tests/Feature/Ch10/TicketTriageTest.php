<?php

use App\Ai\Agents\TicketTriage;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Services\Support\TicketTriageService;
use Illuminate\Support\Facades\Log;

it('stores a validated triage on the ticket', function () {
    TicketTriage::fake([[
        'reason' => 'Money taken twice; a refund is requested.',
        'category' => 'billing',
        'priority' => 'urgent',
        'requires_human' => true,
        'tags' => ['refund', 'double-charge'],
        'summary' => 'Customer charged twice, wants a refund.',
        'customer_name' => 'Asha',
    ]]);

    $ticket = Ticket::factory()->createQuietly();

    $result = app(TicketTriageService::class)->triage($ticket);

    expect($result->category)->toBe(TicketCategory::Billing)
        ->and($result->source)->toBe('ai');

    expect($ticket->fresh())
        ->priority->toBe(TicketPriority::Urgent)
        ->requires_human->toBeTrue()
        ->tags->toBe(['refund', 'double-charge'])
        ->triaged_by->toBe('ai');
});

it('falls back safely when the output is invalid', function () {
    Log::spy();

    TicketTriage::fake([[
        'category' => 'pizza',
        'priority' => 'whenever',
        'requires_human' => 'maybe',
        'tags' => ['a', 'b', 'c', 'd'],
        'summary' => '',
    ]]);

    $ticket = Ticket::factory()->createQuietly();

    $result = app(TicketTriageService::class)->triage($ticket);

    expect($result->source)->toBe('fallback')
        ->and($result->requiresHuman)->toBeTrue()
        ->and($ticket->fresh()->category)
        ->toBe(TicketCategory::Other);

    Log::shouldHaveReceived('warning')->withArgs(
        fn ($message, $context) => $message
            === 'Ticket triage output rejected'
            && array_key_exists('category', $context['errors'])
    );
});

it('falls back when the model returns no JSON at all', function () {
    // An unparseable answer reaches the app as an empty array.
    TicketTriage::fake([[]]);

    $result = app(TicketTriageService::class)
        ->triage(Ticket::factory()->createQuietly());

    expect($result->source)->toBe('fallback');
});

it('declares a schema the provider can enforce', function () {
    // Unscripted, the fake invents data that fits the schema.
    TicketTriage::fake();

    $response = (new TicketTriage)->prompt('Where is my parcel?');

    expect($response['category'])->toBeIn(
        array_column(TicketCategory::cases(), 'value')
    )->and($response['requires_human'])->toBeBool()
        ->and(count($response['tags']))->toBeLessThanOrEqual(3);
});

it('never lets the model skip a human for billing', function () {
    TicketTriage::fake([[
        'reason' => 'Simple refund.',
        'category' => 'billing',
        'priority' => 'normal',
        'requires_human' => false,
        'tags' => [],
        'summary' => 'Wants a refund.',
        'customer_name' => null,
    ]]);

    $result = app(TicketTriageService::class)
        ->triage(Ticket::factory()->createQuietly());

    expect($result->requiresHuman)->toBeTrue();
});
