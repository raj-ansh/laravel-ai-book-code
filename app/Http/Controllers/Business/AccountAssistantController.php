<?php

namespace App\Http\Controllers\Business;

use App\Ai\Agents\AccountAssistant;
use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountAssistantController extends Controller
{
    /**
     * The features to show in the user's interface.
     */
    public function features(
        Request $request,
        AiAccess $access,
    ): JsonResponse {
        return response()->json(array_map(
            fn (AiFeature $feature) => $feature->value,
            $access->features($request->user()),
        ));
    }

    /**
     * Ask about the company's orders. The route's can:use-ai
     * middleware has already checked the plan.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $response = AccountAssistant::make(user: $request->user())
            ->prompt($request->input('message'));

        return response()->json(['reply' => $response->text]);
    }
}
