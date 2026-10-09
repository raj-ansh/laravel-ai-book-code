<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\PendingStep;

class TrimHistory
{
    public function __construct(private int $keep = 12) {}

    /**
     * Send only the recent messages to the model.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        $messages = array_values($step->messages);
        $start = max(0, count($messages) - $this->keep);

        // Start at a user message, so that no tool result is
        // sent without the call that asked for it.
        while ($start > 0
            && $messages[$start]->role !== MessageRole::User) {
            $start--;
        }

        if ($start === 0) {
            return $next($step);
        }

        return $next($step->withMessages(
            array_slice($messages, $start),
        ));
    }
}
