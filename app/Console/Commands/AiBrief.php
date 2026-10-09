<?php

namespace App\Console\Commands;

use App\Ai\Agents\ManagerBriefing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:brief {file} {--prompt-version=}')]
#[Description('Brief a manager on a document')]
class AiBrief extends Command
{
    public function handle(): int
    {
        $agent = new ManagerBriefing(
            version: $this->option('prompt-version'),
        );

        $response = $agent->prompt(
            file_get_contents($this->argument('file')),
        );

        $this->line($response->text);
        $this->newLine();
        $this->components->info(sprintf(
            'prompt %s/%s, %d tokens in, %d out',
            $agent->instructions()->name,
            $agent->instructions()->version,
            $response->usage->inputTokens,
            $response->usage->outputTokens,
        ));

        return self::SUCCESS;
    }
}
