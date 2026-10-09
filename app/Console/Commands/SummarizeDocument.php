<?php

namespace App\Console\Commands;

use App\Services\Text\LongDocumentSummarizer;
use App\Services\Text\TextChunker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('docs:summarize {path} {--chunk=6000}')]
#[Description('Summarise a long text file, chunk by chunk')]
class SummarizeDocument extends Command
{
    public function handle(): int
    {
        $summarizer = new LongDocumentSummarizer(
            new TextChunker((int) $this->option('chunk')),
        );

        $started = microtime(true);
        $summary = $summarizer->summarize(
            file_get_contents($this->argument('path'))
        );

        $this->line($summary->text);
        $this->newLine();
        $this->line(sprintf(
            '%d section(s), %.1fs',
            $summary->sections,
            microtime(true) - $started,
        ));

        if ($summary->truncated) {
            $this->warn('The summary hit the token limit.');
        }

        if ($summary->unsupportedNumbers) {
            $this->warn('Numbers not in the source: '
                .implode(', ', $summary->unsupportedNumbers));
        }

        return self::SUCCESS;
    }
}
