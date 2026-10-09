<?php

namespace App\Console\Commands;

use App\Ai\Agents\SupportAgent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\ToolCall;

#[Signature('support:ask {email} {question}')]
#[Description('Ask the support agent as a customer')]
class SupportAsk extends Command
{
    public function handle(): int
    {
        $customer = User::where('email', $this->argument('email'))
            ->firstOrFail();

        $response = SupportAgent::make(customer: $customer)
            ->prompt($this->argument('question'));

        foreach ($response->steps as $i => $step) {
            $this->line(sprintf(
                'step %d: %s %s',
                $i + 1,
                $step->finishReason->value,
                $this->calls($step),
            ));
        }

        $this->newLine();

        if ($response->text === '') {
            $this->components->warn(
                'No answer: the agent used all of its steps.'
            );
        }

        $this->line($response->text);
        $this->newLine();
        $this->components->info(sprintf(
            '%d steps, %d tokens in, %d out',
            $response->steps->count(),
            $response->usage->inputTokens,
            $response->usage->outputTokens,
        ));

        return self::SUCCESS;
    }

    private function calls(Step $step): string
    {
        return implode(', ', array_map(
            fn (ToolCall $call) => $call->name.'('
                .json_encode($call->arguments).')',
            $step->toolCalls,
        ));
    }
}
