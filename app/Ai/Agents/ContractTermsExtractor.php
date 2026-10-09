<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.0)]
#[MaxTokens(300)]
class ContractTermsExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You copy the key terms of a supplier contract into JSON.
            Copy numbers exactly as written; use null when the
            contract does not state a term. Never calculate or
            guess.

            - payment_days: days Acme has to pay an invoice.
            - notice_days: days of written notice to end it.
            - late_penalty_percent: penalty per week for late
              delivery, as a percentage.
            - late_penalty_cap_percent: the most that penalty can
              reach, as a percentage.
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_days' => $schema->integer()->nullable()
                ->required(),
            'notice_days' => $schema->integer()->nullable()
                ->required(),
            'late_penalty_percent' => $schema->number()->nullable()
                ->required(),
            'late_penalty_cap_percent' => $schema->number()
                ->nullable()->required(),
        ];
    }
}
