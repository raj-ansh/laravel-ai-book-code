<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\ToolCall;

class LogAgentSteps
{
    /**
     * Log what each step cost and did, never what it said.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        $start = hrtime(true);

        return $next($step)->then(
            function (StepResponse $response) use ($step, $start) {
                Log::info('Agent step', [
                    'invocation_id' => $step->invocationId,
                    'step' => $step->number + 1,
                    'model' => $step->provider.'/'.$step->model,
                    'tools' => array_map(
                        fn (ToolCall $call) => $call->name,
                        $response->toolCalls,
                    ),
                    'finish' => $response->finishReason->value,
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'ms' => intdiv(hrtime(true) - $start, 1_000_000),
                ]);
            },
        );
    }
}
