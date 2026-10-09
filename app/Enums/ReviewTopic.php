<?php

namespace App\Enums;

enum ReviewTopic: string
{
    case Quality = 'quality';
    case Delivery = 'delivery';
    case Price = 'price';
    case Service = 'service';
    case Other = 'other';

    /**
     * What the option means, for the model.
     */
    public function description(): string
    {
        return match ($this) {
            self::Quality => 'The product itself: build, defects, '
                .'how well it works.',
            self::Delivery => 'Shipping, packaging, delays, damage '
                .'in transit.',
            self::Price => 'Value for money, discounts, cost.',
            self::Service => 'Contact with Acme staff, returns, '
                .'refunds.',
            self::Other => 'Anything else.',
        };
    }
}
