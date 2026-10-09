<?php

namespace App\Console\Commands;

use App\Models\AiSpan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('ai:trace {trace? : Trace id; the latest if omitted}')]
#[Description('Show one AI trace as a tree of agents, steps and tools')]
class ShowAiTrace extends Command
{
    public function handle(): int
    {
        $trace = $this->argument('trace')
            ?? AiSpan::latest('id')->value('trace_id');
        $spans = AiSpan::where('trace_id', $trace)
            ->orderBy('id')->get();

        if ($spans->isEmpty()) {
            $this->warn('No such trace.');

            return self::FAILURE;
        }

        $this->line("trace {$trace}");
        $root = $spans->firstWhere('span_id', $trace)
            ?? $spans->firstWhere('parent_id', null);
        $this->print($root, $spans, 0);

        return self::SUCCESS;
    }

    private function print(
        AiSpan $span,
        Collection $all,
        int $depth,
    ): void {
        $tokens = $span->input_tokens === null ? ''
            : " {$span->input_tokens} in / {$span->output_tokens} out";
        $error = $span->error ? "  ! {$span->error}" : '';

        $this->line(str_repeat('  ', $depth + 1)
            ."{$span->kind} {$span->name}  "
            .number_format($span->duration_ms)." ms{$tokens}{$error}");

        // Children in the order they finished.
        $all->where('parent_id', $span->span_id)
            ->each(fn (AiSpan $child) => $this->print(
                $child, $all, $depth + 1));
    }
}
