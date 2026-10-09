<?php

namespace App\Evaluation;

use App\Ai\Agents\IntakeCaseExtractor;
use App\Evaluation\Concerns\ChecksFields;

/**
 * Chapter 41: category and order number from a message and
 * what its attachment said.
 */
class IntakeCaseSuite implements EvalSuite
{
    use ChecksFields;

    public function name(): string
    {
        return 'intake-cases';
    }

    public function cases(): array
    {
        return $this->load('intake-cases.json');
    }

    public function prompt(): string
    {
        return (string) (new IntakeCaseExtractor)->instructions();
    }

    public function run(array $case, string $model): array
    {
        // The same prompt shape as IntakeProcessor builds.
        $prompt = "Customer message:\n{$case['message']}"
            .(isset($case['evidence'])
                ? "\n\nAttachment:\n{$case['evidence']}" : '');

        $output = IntakeCaseExtractor::make()->prompt(
            $prompt, provider: 'ollama', model: $model,
        )->toArray();

        return [
            'output' => $output,
            'checks' => $this->fieldChecks($case, $output),
        ];
    }
}
