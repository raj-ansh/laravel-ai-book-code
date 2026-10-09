<?php

namespace App\Http\Middleware;

use App\Services\Ai\TokenBudget;
use App\Support\AiContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For AI routes, after authentication: say who the usage
 * belongs to, and stop requests over the token budget.
 */
class MeterAi
{
    public function __construct(private TokenBudget $budget) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Read by RecordAiUsage, here and in any job this
        // request dispatches.
        AiContext::forUser($user);

        if ($this->budget->exceeded($user)) {
            return response()->json([
                'message' => 'The AI allowance for this period '
                    .'has been used. It renews at the start of '
                    .'the next period.',
            ], 429);
        }

        return $next($request);
    }
}
