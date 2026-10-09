<?php

namespace App\Services\Documents;

use App\Ai\Agents\DocumentAnswerer;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The deterministic alternative to HandbookAgent: code reads
 * every policy file and the model only has to answer.
 * Suitable while the handbook is small.
 */
class HandbookAnswerService
{
    public function ask(string $question): AgentResponse
    {
        $disk = Storage::disk('kb');

        $documents = collect($disk->files('policies'))
            ->map(fn (string $path) => "<document path=\"{$path}\">\n"
                .$disk->get($path)."\n</document>")
            ->implode("\n\n");

        return (new DocumentAnswerer)->prompt(
            "{$documents}\n\nQuestion: {$question}"
        );
    }
}
