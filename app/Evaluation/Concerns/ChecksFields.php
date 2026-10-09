<?php

namespace App\Evaluation\Concerns;

trait ChecksFields
{
    /**
     * One check per expected field. A case may "accept" other
     * values where the label is genuinely ambiguous.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $output
     * @return array<string, bool>
     */
    protected function fieldChecks(array $case, array $output): array
    {
        $checks = [];

        foreach ($case['expect'] as $field => $expected) {
            $actual = strtoupper(trim(
                (string) ($output[$field] ?? ''),
            ));
            $allowed = array_map(
                fn ($value) => strtoupper((string) $value),
                $case['accept'][$field] ?? [$expected],
            );

            $checks[$field] = in_array($actual, $allowed, true);
        }

        return $checks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function load(string $file): array
    {
        return json_decode(file_get_contents(
            resource_path("eval/{$file}")), true);
    }
}
