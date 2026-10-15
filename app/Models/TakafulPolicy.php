<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider', 'policy_number', 'description', 'premium', 'cover_amount', 'start_date', 'end_date',
    'premium_status', 'paid_at', 'paid_reference', 'created_by',
])]
class TakafulPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'premium' => 'integer',
            'cover_amount' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function isCurrent(): bool
    {
        return $this->start_date->lte(now()) && $this->end_date->gte(now()->startOfDay());
    }

    public function expiresWithin(int $days): bool
    {
        return $this->end_date->gte(now()->startOfDay()) && $this->end_date->lte(now()->startOfDay()->addDays($days));
    }
}
