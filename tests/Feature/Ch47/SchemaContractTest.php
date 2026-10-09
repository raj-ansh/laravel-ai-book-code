<?php

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;

/**
 * The JSON schema an agent sends to the provider, exactly as
 * the SDK builds it. The constructor is skipped: schema()
 * must not depend on who the agent is talking to.
 */
function schemaOf(string $agent): array
{
    $instance = (new ReflectionClass($agent))
        ->newInstanceWithoutConstructor();

    return (new ObjectSchema(
        $instance->schema(new JsonSchemaTypeFactory),
    ))->toSchema();
}

/**
 * Every agent in app/Ai/Agents with structured output. A
 * plain path: datasets are built before the app boots.
 */
function structuredAgents(): array
{
    return collect(glob(__DIR__.'/../../../app/Ai/Agents/*.php'))
        ->map(fn (string $file) => 'App\\Ai\\Agents\\'
            .basename($file, '.php'))
        ->filter(fn (string $class) => is_subclass_of(
            $class, HasStructuredOutput::class))
        ->values()->all();
}

it('keeps each structured output schema as agreed', function (
    string $agent,
) {
    // A changed schema changes what every caller receives:
    // review the diff, then run with --update-snapshots.
    expect(schemaOf($agent))->toMatchSnapshot();
})->with(fn () => structuredAgents());

it('requires every field, with no extra fields', function (
    string $agent,
) {
    $schema = schemaOf($agent);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])
        ->toEqualCanonicalizing(array_keys($schema['properties']));
})->with(fn () => structuredAgents());

it('names fields in snake_case', function (string $agent) {
    foreach (array_keys(schemaOf($agent)['properties']) as $field) {
        expect($field)->toBe(Str::snake($field));
    }
})->with(fn () => structuredAgents());
