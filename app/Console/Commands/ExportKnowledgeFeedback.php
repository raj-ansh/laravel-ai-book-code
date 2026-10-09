<?php

namespace App\Console\Commands;

use App\Ai\Security\Pseudonymiser;
use App\Models\KnowledgeAnswer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('knowledge:feedback-export {--days=30}')]
#[Description('Unhelpful answers as evaluation candidates')]
class ExportKnowledgeFeedback extends Command
{
    /**
     * People said these answers did not help. Each becomes a
     * candidate case for Chapter 48's evaluation, once a
     * person has written down what the right answer was.
     */
    public function handle(): int
    {
        $answers = KnowledgeAnswer::where('helpful', false)
            ->where('created_at', '>=',
                now()->subDays((int) $this->option('days')))
            ->get();

        $cases = $answers->map(function (KnowledgeAnswer $answer) {
            // Real customers' words: no personal data leaves.
            $names = new Pseudonymiser;

            return [
                'id' => "feedback-{$answer->id}",
                'question' => $names->hide($answer->question),
                'given' => $names->hide($answer->reply),
                'route' => $answer->route,
                'feedback' => $names->hide((string) $answer->feedback),
                'expect' => null, // written by a person
            ];
        })->values()->all();

        Storage::disk('local')->put('eval/knowledge-feedback.json',
            json_encode($cases, JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->line(count($cases).' case(s) written to '
            .'storage/app/private/eval/knowledge-feedback.json');

        return self::SUCCESS;
    }
}
