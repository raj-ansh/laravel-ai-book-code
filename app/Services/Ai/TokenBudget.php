<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Models\User;

/**
 * Token allowances: per organisation per month (by plan),
 * per consumer per day. Staff are not limited.
 */
class TokenBudget
{
    /**
     * @return array{limit: ?int, used: int}
     */
    public function status(User $user): array
    {
        if ($user->is_staff) {
            return ['limit' => null, 'used' => 0];
        }

        $budgets = config('acme.ai.token_budgets');
        $organisation = $user->organisation;

        [$limit, $usage] = $organisation
            ? [$budgets[$organisation->ai_plan] ?? 0,
                AiUsage::where('organisation_id', $organisation->id)
                    ->where('created_at', '>=', now()->startOfMonth())]
            : [$budgets['consumer_daily'],
                AiUsage::where('user_id', $user->id)
                    ->where('created_at', '>=', now()->startOfDay())];

        return ['limit' => $limit, 'used' => (int) $usage
            ->sum(AiUsage::raw('input_tokens + output_tokens'))];
    }

    public function exceeded(User $user): bool
    {
        ['limit' => $limit, 'used' => $used] = $this->status($user);

        return $limit !== null && $used >= $limit;
    }
}
