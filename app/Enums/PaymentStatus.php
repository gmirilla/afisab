<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingConfirmation => 'Awaiting confirmation',
            self::Confirmed => 'Confirmed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }
}
