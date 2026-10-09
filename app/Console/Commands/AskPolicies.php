<?php

namespace App\Console\Commands;

use App\Services\Rag\PolicyQuestionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('policies:ask {question}')]
#[Description('Answer a question from the policy handbook')]
class AskPolicies extends Command
{
    public function handle(PolicyQuestionService $service): int
    {
        $answer = $service->ask($this->argument('question'));

        $this->line($answer->text);

        if ($answer->answered && ! $answer->cited) {
            $this->warn('  (no citations: check the sources)');
        }

        foreach ($answer->sources as $source) {
            $this->line("  [{$source['number']}] {$source['title']}: "
                .Str::limit(Str::squish($source['content']), 50));
        }

        return self::SUCCESS;
    }
}
