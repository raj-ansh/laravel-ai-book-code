<?php

namespace App\Listeners;

use App\Models\AiUsage;
use App\Services\Ai\AiPricing;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Events\StepCompleted;

/**
 * Meters every agent step. Who it was for comes from the
 * request's Context, which queued jobs carry with them.
 */
class RecordAiUsage
{
    public function __construct(private AiPricing $pricing) {}

    public function handle(StepCompleted $event): void
    {
        $usage = $event->response->usage;
        $provider = $event->provider->name();

        AiUsage::create([
            'user_id' => Context::get('ai_user_id'),
            'organisation_id' => Context::get('ai_organisation_id'),
            'feature' => class_basename($event->agent),
            'provider' => $provider,
            'model' => $event->model,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cost_micros' => $this->pricing->costMicros(
                $provider, $event->model,
                $usage->inputTokens, $usage->outputTokens,
            ),
            'invocation_id' => $event->invocationId,
        ]);
    }
}
