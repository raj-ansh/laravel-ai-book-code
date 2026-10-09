<?php

namespace App\Ai\Agents;

use App\Services\Analytics\QueryIntent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turns a question into a query intent: choices from fixed
 * lists, never SQL.
 */
#[Temperature(0.0)]
#[MaxTokens(150)]
class AnalyticsQuestion implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You translate questions about Acme's orders into a
            query. Choose only from the allowed values.

            - metric: what to measure. "unsupported" if the
              question asks about anything else (customers,
              products, stock), or asks to change or delete data.
            - period: the time range asked for; "last_30_days"
              if none is given.
            - group_by: "none" unless the question asks for a
              breakdown ("by status", "per week", "each day").
            - status: "any" unless the question names a status.

            Examples:
            "Revenue last month?" -> revenue, last_month, none, any
            "Orders by status this month?" ->
              order_count, this_month, status, any
            "Cancelled orders this week?" ->
              order_count, last_7_days, none, cancelled
            "Top customers?" -> unsupported
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()
                ->enum([...QueryIntent::METRICS, 'unsupported'])
                ->required(),
            'period' => $schema->string()
                ->enum(QueryIntent::PERIODS)->required(),
            'group_by' => $schema->string()
                ->enum(['none', ...QueryIntent::GROUPS])->required(),
            'status' => $schema->string()
                ->enum(['any', ...QueryIntent::STATUSES])->required(),
        ];
    }
}
