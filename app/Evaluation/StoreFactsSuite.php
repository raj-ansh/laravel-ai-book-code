<?php

namespace App\Evaluation;

use App\Ai\Agents\StoreInfoAssistant;
use App\Evaluation\Concerns\ChecksFields;
use Illuminate\Support\Str;

/**
 * Chapter 45: does the answer contain the facts it must,
 * and none it must not?
 */
class StoreFactsSuite implements EvalSuite
{
    use ChecksFields;

    public function name(): string
    {
        return 'store-facts';
    }

    public function cases(): array
    {
        return $this->load('store-facts.json');
    }

    public function prompt(): string
    {
        return (string) (new StoreInfoAssistant)->instructions();
    }

    public function run(array $case, string $model): array
    {
        $answer = StoreInfoAssistant::make()->prompt(
            $case['question'], provider: 'ollama', model: $model,
        )->text;

        $checks = [];
        foreach ($case['must_contain'] as $fact) {
            $checks["has {$fact}"] = Str::contains(
                $answer, $fact, ignoreCase: true);
        }
        foreach ($case['must_not_contain'] ?? [] as $wrong) {
            $checks["not {$wrong}"] = ! Str::contains(
                $answer, $wrong, ignoreCase: true);
        }

        return ['output' => ['answer' => $answer], 'checks' => $checks];
    }
}
