<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Jobs\InspectDamagePhoto;
use App\Models\DamageReport;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DamageReportController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
    ): JsonResponse {
        Gate::authorize('view', $order);

        $request->validate([
            'photo' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200',
            ],
        ]);

        $report = DamageReport::create([
            'order_id' => $order->id,
            'photo_path' => $request->file('photo')
                ->store("damage/{$order->id}", 'local'),
            'status' => 'pending',
        ]);

        InspectDamagePhoto::dispatch($report);

        return response()->json(['id' => $report->id], 202);
    }
}
