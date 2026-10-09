<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Rag\QuotedAnswerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Signature('policies:quote {question} {--previous=}
    {--as= : Ask as this user\'s email (Chapter 35)}')]
#[Description('Answer from the policies with verified quotes')]
class AskPoliciesWithQuotes extends Command
{
    public function handle(): int
    {
        if ($email = $this->option('as')) {
            Auth::setUser(User::where('email', $email)->firstOrFail());
        }

        // Resolved after the user is known, so retrieval is scoped.
        $service = app(QuotedAnswerService::class);

        $answer = $service->ask(
            $this->argument('question'), $this->option('previous'),
        );

        $this->line($answer->text);

        foreach ($answer->sources as $source) {
            $this->line("  [{$source['number']}] {$source['title']}: "
                .Str::limit(Str::squish($source['content']), 50));
        }

        return self::SUCCESS;
    }
}
