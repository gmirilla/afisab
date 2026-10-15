<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trade_cycle_id', 'category', 'description', 'amount', 'status', 'proposed_by', 'paid_by', 'paid_at', 'payment_reference',
])]
class CycleExpense extends Model
{
    public const PROPOSED = 'proposed';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CATEGORIES = ['transport', 'storage', 'labour', 'packaging', 'other'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TradeCycle::class, 'trade_cycle_id');
    }
}
