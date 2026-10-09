<?php

namespace App\Ai\Prompts;

use Stringable;

final readonly class VersionedPrompt implements Stringable
{
    public function __construct(
        public string $name,
        public string $version,
        public string $text,
    ) {}

    public function __toString(): string
    {
        return $this->text;
    }
}
