<?php

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;
use Throwable;

use function Laravel\Ai\agent;

#[Signature('ai:check {--skip-embeddings}')]
#[Description('Check that the default AI providers answer')]
class AiCheck extends Command
{
    public function handle(): int
    {
        $ok = $this->check('text', function () {
            $response = agent('Reply with one word: pong')
                ->prompt('ping', timeout: 30);

            return $response->meta->provider.' / '
                .$response->meta->model;
        });

        if (! $this->option('skip-embeddings')) {
            $ok = $this->check('embeddings', function () {
                $response = Embeddings::for(['ping'])
                    ->generate();

                return $response->meta->provider.' / '
                    .$response->meta->model.', '
                    .count($response->first()).' dimensions';
            }) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, Closure $probe): bool
    {
        $started = microtime(true);

        try {
            $detail = $probe();
        } catch (Throwable $e) {
            $this->components->error(
                "{$name}: ".$e->getMessage()
            );

            return false;
        }

        $ms = (int) ((microtime(true) - $started) * 1000);

        $this->components->info("{$name}: {$detail} ({$ms} ms)");

        return true;
    }
}
