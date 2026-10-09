<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\ClassificationProvider;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\RerankingProvider;
use Laravel\Ai\Contracts\Providers\StoreProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Throwable;

#[Signature('ai:providers')]
#[Description('Show what each configured AI provider can do')]
class AiProviders extends Command
{
    /** Column heading => capability contract. */
    private const CAPABILITIES = [
        'text' => TextProvider::class,
        'embed' => EmbeddingProvider::class,
        'rerank' => RerankingProvider::class,
        'image' => ImageProvider::class,
        'tts' => AudioProvider::class,
        'stt' => TranscriptionProvider::class,
        'classify' => ClassificationProvider::class,
        'stores' => StoreProvider::class,
    ];

    public function handle(): int
    {
        $rows = [];

        foreach (array_keys(config('ai.providers')) as $name) {
            try {
                $provider = Ai::instance($name);
            } catch (Throwable) {
                continue; // e.g. Bedrock without the AWS SDK
            }

            $row = [$name];

            foreach (self::CAPABILITIES as $contract) {
                $row[] = $provider instanceof $contract
                    ? 'yes' : '-';
            }

            $rows[] = $row;
        }

        $this->table(
            ['provider', ...array_keys(self::CAPABILITIES)],
            $rows,
        );

        return self::SUCCESS;
    }
}
