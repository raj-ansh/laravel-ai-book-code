<?php

namespace App\Ai\Middleware;

use App\Ai\Security\Pseudonymiser;
use Closure;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\PendingStep;

/**
 * The provider sees [EMAIL_1] and [CARD_1]; the customer
 * and the tools see the real values.
 */
class PseudonymisePersonalData
{
    private Pseudonymiser $pseudonyms;

    public function __construct()
    {
        $this->pseudonyms = new Pseudonymiser;
    }

    public function handle(PendingStep $step, Closure $next)
    {
        $messages = array_map(
            fn (Message $message) => $this->hide($message),
            $step->messages,
        );

        return $next($step->withMessages($messages))
            ->then(function (StepResponse $response) {
                $response->text = $this->pseudonyms
                    ->restore($response->text);

                // A tool asked for "[EMAIL_1]" gets the address.
                foreach ($response->toolCalls as $call) {
                    $call->arguments = $this->pseudonyms
                        ->restoreAll($call->arguments);
                }
            });
    }

    /**
     * User and assistant text is hidden. Tool results are
     * not: keep personal data out of them at the source.
     */
    private function hide(Message $message): Message
    {
        if (! is_string($message->content)) {
            return $message;
        }

        $copy = clone $message;
        $copy->content = $this->pseudonyms->hide($message->content);

        return $copy;
    }
}
