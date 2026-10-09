<?php

namespace App\Services\Ai;

use App\Enums\AiFeature;
use App\Models\User;

/**
 * Which AI features a user may use: staff get everything,
 * business customers what their organisation's plan
 * includes, everyone else the consumer list.
 */
class AiAccess
{
    public function allows(User $user, AiFeature $feature): bool
    {
        return in_array($feature, $this->features($user), true);
    }

    /**
     * @return list<AiFeature>
     */
    public function features(User $user): array
    {
        if ($user->is_staff) {
            return AiFeature::cases();
        }

        $plan = $user->organisation?->ai_plan ?? 'consumer';

        return array_map(
            fn (string $name) => AiFeature::from($name),
            config("acme.ai.plans.{$plan}", []),
        );
    }
}
