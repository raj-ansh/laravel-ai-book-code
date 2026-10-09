<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskDocumentRequest;
use App\Services\Documents\AskDocumentService;
use Illuminate\Http\JsonResponse;

class DocumentQuestionController extends Controller
{
    public function __invoke(
        AskDocumentRequest $request,
        AskDocumentService $documents,
    ): JsonResponse {
        $response = $documents->ask(
            $request->file('file'),
            $request->string('question'),
        );

        return response()->json(['answer' => $response->text]);
    }
}
