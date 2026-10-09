<?php

namespace App\Ai\Prompts;

use InvalidArgumentException;

/**
 * Loads prompt text from resources/prompts/{name}/{version}.md
 * and fills in {{ placeholders }}.
 */
class PromptLibrary
{
    /**
     * @param  array<string, string>  $data
     */
    public function get(
        string $name,
        array $data = [],
        ?string $version = null,
    ): VersionedPrompt {
        $version ??= config("acme.ai.prompts.{$name}", 'v1');

        if (! preg_match('/^[a-z0-9-]+$/', $name.$version)) {
            throw new InvalidArgumentException(
                'Prompt names and versions use a-z, 0-9 and "-".'
            );
        }

        $path = resource_path("prompts/{$name}/{$version}.md");

        if (! is_file($path)) {
            throw new InvalidArgumentException(
                "Prompt [{$name}/{$version}] does not exist."
            );
        }

        $text = preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $match) => $data[$match[1]]
                ?? throw new InvalidArgumentException(
                    "Prompt [{$name}] needs [{$match[1]}]."
                ),
            trim(file_get_contents($path)),
        );

        return new VersionedPrompt($name, $version, $text);
    }
}
