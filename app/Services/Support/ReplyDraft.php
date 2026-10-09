<?php

namespace App\Services\Support;

final readonly class ReplyDraft
{
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
