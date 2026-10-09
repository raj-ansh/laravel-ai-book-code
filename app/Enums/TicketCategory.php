<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case Delivery = 'delivery';
    case Security = 'security';
    case Other = 'other';
}
