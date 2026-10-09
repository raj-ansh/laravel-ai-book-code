<?php

namespace App\Console\Commands;

use App\Ai\Agents\QuickAnswer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('ai:compare {prompt} {targets*}')]
#[Description('Run one prompt against several models')]
class AiCompare extends Command
{
    public function handle(): int
    {
        $rows = [];

        foreach ($this->argument('targets') as $target) {
            [$provider, $model] = explode(':', $target, 2);
            $started = microtime(true);

            try {
                $response = (new QuickAnswer)
                    ->prompt(
                        $this->argument('prompt'),
                        provider: $provider,
                        model: $model,
                    );
            } catch (Throwable $e) {
                $rows[] = [$target, '-', '-', '-',
                    Str::limit($e->getMessage(), 40)];

                continue;
            }

            $rows[] = [
                $target,
                (int) ((microtime(true) - $started) * 1000),
                $response->usage->inputTokens,
                $response->usage->outputTokens,
                Str::limit($response->text, 40),
            ];
        }

        $this->table(
            ['target', 'ms', 'in', 'out', 'answer'],
            $rows,
        );

        return self::SUCCESS;
    }
}
