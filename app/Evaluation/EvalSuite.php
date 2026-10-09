<?php

namespace App\Evaluation;

/**
 * A labelled dataset and a way to score one case of it.
 */
interface EvalSuite
{
    public function name(): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function cases(): array;

    /**
     * The instructions under test, so a run records which
     * version of the prompt it measured.
     */
    public function prompt(): string;

    /**
     * Run one case on the given model and return what came
     * out and which named checks it passed.
     *
     * @param  array<string, mixed>  $case
     * @return array{output: mixed, checks: array<string, bool>}
     */
    public function run(array $case, string $model): array;
}
