<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Knowledge\KnowledgePlatform;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:ask {email} {question} {--conversation=}')]
#[Description('Ask the Acme Knowledge Platform as a user')]
class AskKnowledge extends Command
{
    public function handle(KnowledgePlatform $platform): int
    {
        $user = User::where('email', $this->argument('email'))
            ->firstOrFail();

        $answer = $platform->ask($user, $this->argument('question'),
            $this->option('conversation'));

        $this->line("[{$answer->route}] {$answer->reply}");
        foreach ($answer->citations as $citation) {
            $this->line("  - {$citation['title']}: "
                ."\"{$citation['quote']}\"");
        }
        $this->line("  conversation {$answer->conversation_id}, "
            ."answer {$answer->id}");

        return self::SUCCESS;
    }
}
