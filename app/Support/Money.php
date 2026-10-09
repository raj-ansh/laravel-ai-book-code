<?php

namespace App\Support;

final class Money
{
    /**
     * 459900 => "INR 4,599.00": always with the currency, so a
     * model never has to guess it.
     */
    public static function inr(int $cents): string
    {
        return 'INR '.number_format($cents / 100, 2);
    }
}
