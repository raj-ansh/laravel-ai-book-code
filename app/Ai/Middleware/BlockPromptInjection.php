<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

class BlockPromptInjection
{
    /** Phrases that have no place in a support question. */
    private const PATTERNS = [
        '/ignore (all |any )?(previous|prior|above) instructions/i',
        '/(reveal|print|show).{0,20}(system prompt|instructions)/i',
        '/you are now|developer mode|jailbreak/i',
    ];

    public const REPLY = 'I can only help with questions about '
        .'your Acme orders and account.';

    /**
     * Answer suspicious prompts without calling the model.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        $prompt = $this->latestUserMessage($step);

        if (! $step->isFirstStep() || ! $this->suspicious($prompt)) {
            return $next($step);
        }

        Log::warning('Prompt blocked', [
            'invocation_id' => $step->invocationId,
            'prompt' => Str::limit($prompt, 200),
        ]);

        return new StepResponse(
            text: self::REPLY,
            toolCalls: [],
            finishReason: FinishReason::Stop,
            usage: new TextUsage,
            meta: new Meta($step->provider, $step->model),
        );
    }

    private function latestUserMessage(PendingStep $step): string
    {
        $message = collect($step->messages)->last(
            fn (Message $message) => $message->role
                === MessageRole::User,
        );

        return (string) $message?->content;
    }

    private function suspicious(string $prompt): bool
    {
        return collect(self::PATTERNS)->contains(
            fn (string $pattern) => preg_match($pattern, $prompt),
        );
    }
}
