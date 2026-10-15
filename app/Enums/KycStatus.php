<?php

namespace App\Enums;

enum KycStatus: string
{
    case Incomplete = 'incomplete';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
