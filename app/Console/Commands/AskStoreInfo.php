<?php

namespace App\Console\Commands;

use App\Services\Ai\AnswerCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('store:ask {question}')]
#[Description('Answer a store question, cached, with failover')]
class AskStoreInfo extends Command
{
    public function handle(AnswerCache $answers): int
    {
        $start = hrtime(true);
        $answer = $answers->answer($this->argument('question'));
        $ms = intdiv(hrtime(true) - $start, 1_000_000);

        $this->line("[{$answer['source']}, {$ms} ms] "
            .$answer['text']);

        return self::SUCCESS;
    }
}
