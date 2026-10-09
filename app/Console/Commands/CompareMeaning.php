<?php

namespace App\Console\Commands;

use App\Support\Vectors;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;

#[Signature('ai:similarity {texts* : Two or more texts}')]
#[Description('Show how similar texts are in meaning')]
class CompareMeaning extends Command
{
    public function handle(): int
    {
        $texts = $this->argument('texts');
        $prefix = config('acme.ai.embeddings.prefixes.document');

        $response = Embeddings::for(array_map(
            fn (string $text) => $prefix.$text, $texts,
        ))->generate();

        $this->line(sprintf('%s/%s, %d dimensions',
            $response->meta->provider, $response->meta->model,
            count($response->first())));

        foreach ($texts as $i => $text) {
            foreach ($texts as $j => $other) {
                if ($j <= $i) {
                    continue;
                }

                $this->line(sprintf('%.3f  "%s" ~ "%s"',
                    Vectors::cosine(
                        $response->embeddings[$i],
                        $response->embeddings[$j],
                    ), $text, $other));
            }
        }

        return self::SUCCESS;
    }
}
