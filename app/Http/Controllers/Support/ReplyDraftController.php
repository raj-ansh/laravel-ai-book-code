<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\DraftReplyRequest;
use App\Services\Support\ReplyDraftService;
use Illuminate\Http\JsonResponse;

class ReplyDraftController extends Controller
{
    public function __invoke(
        DraftReplyRequest $request,
        ReplyDraftService $drafts,
    ): JsonResponse {
        $draft = $drafts->draft(
            $request->string('message'),
            $request->input('customer_name'),
        );

        return response()->json([
            'draft' => $draft->text,
            'model' => $draft->model,
        ], 201);
    }
}
