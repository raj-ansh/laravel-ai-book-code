<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:ask {question}')]
#[Description('Answer a question about orders with checked figures')]
class AskAnalytics extends Command
{
    public function handle(AnalyticsAssistant $assistant): int
    {
        $answer = $assistant->ask($this->argument('question'));

        if ($answer['intent']) {
            $this->line('Query: '.$answer['intent']->describe());
            $this->table(['', 'Value'], array_map(
                fn (array $row) => [$row['label'], $row['value']],
                $answer['rows'],
            ));
        }

        $this->line($answer['summary']);

        return self::SUCCESS;
    }
}
