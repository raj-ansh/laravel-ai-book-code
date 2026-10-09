<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.6)]
#[MaxTokens(700)]
class ContentWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $voice = config('acme.ai.brand.voice');
        $limits = config('acme.ai.brand.limits');

        return <<<TEXT
            You write marketing copy for Acme, an Indian online
            home store. Brand voice: {$voice}

            From the product facts, write:
            - description: two short paragraphs, at most
              {$limits['description']} characters.
            - seo_title: at most {$limits['seo_title']} characters,
              ending with " | Acme".
            - meta_description: at most
              {$limits['meta_description']} characters.
            - social_post: at most {$limits['social_post']}
              characters, one or two hashtags.

            Use only the facts given. Do not invent features,
            materials, sizes, offers or guarantees. Prices in INR,
            exactly as given.
            TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->required(),
            'seo_title' => $schema->string()->required(),
            'meta_description' => $schema->string()->required(),
            'social_post' => $schema->string()->required(),
        ];
    }
}
