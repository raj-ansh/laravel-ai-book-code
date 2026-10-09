<?php

namespace App\Evaluation;

use App\Ai\Agents\AnalyticsQuestion;
use App\Evaluation\Concerns\ChecksFields;

/**
 * Chapter 40: does the model pick the right query intent,
 * and does it say "unsupported" when it should?
 */
class AnalyticsIntentSuite implements EvalSuite
{
    use ChecksFields;

    public function name(): string
    {
        return 'analytics-intents';
    }

    public function cases(): array
    {
        return $this->load('analytics-intents.json');
    }

    public function prompt(): string
    {
        return (string) (new AnalyticsQuestion)->instructions();
    }

    public function run(array $case, string $model): array
    {
        $output = AnalyticsQuestion::make()->prompt(
            $case['question'], provider: 'ollama', model: $model,
        )->toArray();

        return [
            'output' => $output,
            'checks' => $this->fieldChecks($case, $output),
        ];
    }
}
