<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\Ai\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiUsageController extends Controller
{
    /**
     * The data behind the AI usage dashboard.
     */
    public function __invoke(
        Request $request,
        UsageReport $report,
    ): JsonResponse {
        $days = $request->integer('days', 30);
        abort_unless($days >= 1 && $days <= 366, 422);

        return response()->json($report->since(
            CarbonImmutable::now()->subDays($days)->startOfDay()
        ));
    }
}
