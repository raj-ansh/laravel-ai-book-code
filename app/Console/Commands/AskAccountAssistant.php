<?php

namespace App\Console\Commands;

use App\Ai\Agents\AccountAssistant;
use App\Enums\AiFeature;
use App\Models\User;
use App\Services\Ai\TokenBudget;
use App\Support\AiContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

#[Signature('business:ask {email} {question}')]
#[Description('Ask the account assistant as a business user')]
class AskAccountAssistant extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))
            ->with('organisation')->firstOrFail();
        $feature = AiFeature::AccountAssistant->value;

        // The same gate the route uses.
        if (Gate::forUser($user)->denies('use-ai', $feature)) {
            $account = $user->organisation->name ?? 'Consumer';
            $this->warn("{$account}: {$feature} is not in "
                .'this plan.');

            return self::FAILURE;
        }

        // Chapter 44: meter and limit, as the route does.
        AiContext::forUser($user);
        $budget = app(TokenBudget::class)->status($user);

        if ($budget['limit'] !== null
            && $budget['used'] >= $budget['limit']) {
            $this->warn("Allowance used: {$budget['used']} of "
                ."{$budget['limit']} tokens.");

            return self::FAILURE;
        }

        $this->line(AccountAssistant::make(user: $user)
            ->prompt($this->argument('question'))->text);

        return self::SUCCESS;
    }
}
