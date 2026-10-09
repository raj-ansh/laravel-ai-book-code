<?php

namespace App\Console\Commands;

use App\Ai\Agents\SupportConcierge;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('support:concierge {email} {question}')]
#[Description('Ask the Chapter 19 concierge (nested agents)')]
class AskConcierge extends Command
{
    public function handle(): int
    {
        $customer = User::where('email', $this->argument('email'))
            ->firstOrFail();

        $this->line(SupportConcierge::make(customer: $customer)
            ->prompt($this->argument('question'))->text);

        return self::SUCCESS;
    }
}
