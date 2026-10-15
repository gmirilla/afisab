<?php

namespace App\Enums;

enum InvestmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Matured = 'matured';
    case PaidOut = 'paid_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Active => 'Active',
            self::Matured => 'Matured',
            self::PaidOut => 'Paid out',
            self::Cancelled => 'Cancelled',
        };
    }
}
