<?php

namespace App\Console\Commands;

use App\Services\Support\FaqMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('faq:match {question}')]
#[Description('Suggest FAQ entries for a question, by meaning')]
class MatchFaq extends Command
{
    public function handle(): int
    {
        $matches = FaqMatcher::fromFile(
            resource_path('samples/faq.json')
        )->match($this->argument('question'));

        if ($matches === []) {
            $this->warn('No FAQ entry is close enough.');

            return self::SUCCESS;
        }

        foreach ($matches as $match) {
            $this->line("{$match['score']}  {$match['q']}");
        }

        return self::SUCCESS;
    }
}
