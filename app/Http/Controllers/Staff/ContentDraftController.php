<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\DraftProductContent;
use App\Models\ContentDraft;
use App\Models\Product;
use App\Services\Content\ContentStudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ContentDraftController extends Controller
{
    /** Ask for new drafts; they arrive in a minute or so. */
    public function store(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage-content');

        DraftProductContent::dispatch($product, $request->user());

        return response()->json(['status' => 'drafting'], 202);
    }

    /** The drafts waiting for a decision, with their issues. */
    public function index(Product $product): JsonResponse
    {
        Gate::authorize('manage-content');

        return response()->json(ContentDraft::query()
            ->where('product_id', $product->id)
            ->where('status', 'draft')
            ->latest()
            ->get(['id', 'kind', 'body', 'issues']));
    }

    public function approve(
        Request $request,
        ContentDraft $draft,
        ContentStudio $studio,
    ): JsonResponse {
        Gate::authorize('manage-content');

        try {
            $studio->approve($draft, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['status' => 'approved']);
    }

    public function reject(
        Request $request,
        ContentDraft $draft,
        ContentStudio $studio,
    ): JsonResponse {
        Gate::authorize('manage-content');
        $studio->reject($draft, $request->user());

        return response()->json(['status' => 'rejected']);
    }
}
