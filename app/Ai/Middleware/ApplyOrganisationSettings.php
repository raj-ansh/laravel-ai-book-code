<?php

namespace App\Ai\Middleware;

use App\Models\Organisation;
use Closure;
use Laravel\Ai\PendingStep;

/**
 * Applies a tenant's AI settings to every step: its model
 * and its tone, each chosen from a list the code controls.
 */
class ApplyOrganisationSettings
{
    public function __construct(private ?Organisation $organisation) {}

    public function handle(PendingStep $step, Closure $next)
    {
        $settings = $this->organisation?->ai_settings ?? [];
        $options = config('acme.ai.tenant_options');

        $model = $settings['model'] ?? null;
        if (in_array($model, $options['models'], true)) {
            $step = $step->withModel($model);
        }

        // The tenant picks a key; the words are ours.
        $tone = $options['tones'][$settings['tone'] ?? ''] ?? null;
        if ($tone) {
            $step = $step->withInstructions(
                $step->instructions."\n\n".$tone
            );
        }

        return $next($step);
    }
}
