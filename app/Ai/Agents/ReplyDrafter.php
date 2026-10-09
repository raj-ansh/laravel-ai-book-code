<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.4)]
#[MaxTokens(400)]
class ReplyDrafter implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You draft replies for Acme's customer support team.
            A human agent reviews every draft before it is sent.

            Write a polite, concise reply to the customer message.
            - Use the customer's name if it is given.
            - Do not promise refunds, discounts or delivery dates.
            - Do not invent order numbers or other facts.
            - If you need information, ask for it.
            - End with: "Best regards, Acme Support"
            TEXT;
    }
}
