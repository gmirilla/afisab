<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Expired = 'expired'; // an attempt that lapsed or was replaced; it can still be honoured if the money turns up and the gateway confirms it

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingConfirmation => 'Awaiting confirmation',
            self::Confirmed => 'Confirmed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }
}
