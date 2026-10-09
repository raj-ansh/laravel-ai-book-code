<?php

namespace App\Console\Commands;

use App\Models\KnowledgeAnswer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:report {--days=7}')]
#[Description('Knowledge Platform routes and ratings')]
class KnowledgeReport extends Command
{
    public function handle(): int
    {
        $answers = KnowledgeAnswer::where('created_at', '>=',
            now()->subDays((int) $this->option('days')))->get();

        if ($answers->isEmpty()) {
            $this->line('No answers in this period.');

            return self::SUCCESS;
        }

        $total = $answers->count();

        // agent: the model searched and found sources itself.
        // fallback: code searched for it. none: not covered.
        $this->table(['Route', 'Answers', 'Share', 'Rated helpful'],
            $answers->groupBy('route')->map(fn ($group, $route) => [
                $route, $group->count(),
                round(100 * $group->count() / $total).'%',
                $this->helpful($group),
            ])->sortBy(0)->values()->all());

        $this->line("{$total} answers; ".$this->helpful($answers)
            .' of rated answers were helpful.');

        return self::SUCCESS;
    }

    private function helpful($answers): string
    {
        $rated = $answers->whereNotNull('helpful');

        return $rated->isEmpty() ? 'n/a'
            : $rated->where('helpful', true)->count()
                .'/'.$rated->count();
    }
}
