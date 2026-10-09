<?php

use App\Ai\Agents\AnalyticsQuestion;
use App\Ai\Agents\ReportNarrator;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Analytics\AnalyticsAssistant;
use App\Services\Analytics\AnalyticsRunner;
use App\Services\Analytics\QueryIntent;
use Carbon\CarbonImmutable;

/** An order placed on the given date. */
function orderOn(
    string $date,
    int $cents,
    string $status = 'delivered',
): Order {
    return Order::factory()->create([
        'total_cents' => $cents,
        'status' => OrderStatus::from($status),
        'created_at' => $date.' 10:00:00',
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-10-06 12:00:00');
    orderOn('2026-09-03', 100000);
    orderOn('2026-09-20', 50000, 'cancelled');
    orderOn('2026-10-02', 20000);
    orderOn('2026-10-05', 40000, 'shipped');
});

it('measures revenue for a period', function () {
    $rows = app(AnalyticsRunner::class)->run(
        new QueryIntent('revenue', 'last_month'),
        CarbonImmutable::now(),
    );

    expect($rows)->toBe([
        ['label' => 'all orders', 'value' => 'INR 1,500.00'],
    ]);
});

it('groups and filters', function () {
    $runner = app(AnalyticsRunner::class);
    $now = CarbonImmutable::now();

    expect($runner->run(new QueryIntent('order_count',
        'last_30_days', 'status'), $now))->toBe([
            ['label' => 'cancelled', 'value' => '1'],
            ['label' => 'delivered', 'value' => '1'],
            ['label' => 'shipped', 'value' => '1'],
        ])
        ->and($runner->run(new QueryIntent('average_order_value',
            'this_month', null, 'delivered'), $now))->toBe([
                ['label' => 'all orders', 'value' => 'INR 200.00'],
            ]);
});

it('rejects values outside the lists', function () {
    new QueryIntent('revenue; DROP TABLE orders', 'last_month');
})->throws(InvalidArgumentException::class);

it('drops a grouping the status filter makes pointless', function () {
    $intent = QueryIntent::fromModel([
        'metric' => 'order_count', 'period' => 'last_7_days',
        'group_by' => 'status', 'status' => 'cancelled',
    ]);

    expect($intent)->groupBy->toBeNull()->status->toBe('cancelled');
});

it('narrates figures that match the results', function () {
    AnalyticsQuestion::fake([[
        'metric' => 'revenue', 'period' => 'last_month',
        'group_by' => 'none', 'status' => 'any',
    ]]);
    ReportNarrator::fake(['Revenue last month was INR 1,500.00.']);

    $answer = app(AnalyticsAssistant::class)
        ->ask('How much revenue did we make last month?');

    expect($answer['summary'])
        ->toBe('Revenue last month was INR 1,500.00.');
});

it('drops a summary with an invented figure', function () {
    AnalyticsQuestion::fake([[
        'metric' => 'revenue', 'period' => 'last_month',
        'group_by' => 'none', 'status' => 'any',
    ]]);
    ReportNarrator::fake([
        'Revenue was INR 1,500.00, up 12% on August.',
    ]);

    $answer = app(AnalyticsAssistant::class)
        ->ask('How much revenue did we make last month?');

    expect($answer['summary'])->toBe('See the figures below.')
        ->and($answer['rows'])->toHaveCount(1);
});

it('refuses without calling the model', function (
    string $question,
) {
    AnalyticsQuestion::fake()->preventStrayPrompts();

    expect(app(AnalyticsAssistant::class)->ask($question))
        ->intent->toBeNull()
        ->summary->toBe(AnalyticsAssistant::REFUSAL)
        ->and(Order::count())->toBe(4);
})->with([
    'Delete all cancelled orders',
    'Which customer spent the most?',
]);

it('refuses when the model says unsupported', function () {
    AnalyticsQuestion::fake([[
        'metric' => 'unsupported', 'period' => 'last_30_days',
        'group_by' => 'none', 'status' => 'any',
    ]]);

    expect(app(AnalyticsAssistant::class)->ask('Is it raining?'))
        ->intent->toBeNull();
});
