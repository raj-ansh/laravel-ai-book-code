<?php

namespace App\Console\Commands;

use App\Ai\Agents\Explainer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:anatomy {question}')]
#[Description('Ask the Explainer agent and show what came back')]
class AiAnatomy extends Command
{
    public function handle(): int
    {
        $started = microtime(true);

        $response = (new Explainer)
            ->prompt($this->argument('question'));

        $ms = (int) ((microtime(true) - $started) * 1000);

        $this->line($response->text);
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['provider', $response->meta->provider],
            ['model', $response->meta->model],
            ['input tokens', $response->usage->inputTokens],
            ['output tokens', $response->usage->outputTokens],
            ['time', "{$ms} ms"],
        ]);

        return self::SUCCESS;
    }
}
