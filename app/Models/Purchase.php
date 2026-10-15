<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference', 'trade_cycle_id', 'supplier_id', 'quantity_kg', 'unit_price', 'total', 'status',
    'proposed_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'payment_reference', 'received_at', 'note',
])]
class Purchase extends Model
{
    public const PROPOSED = 'proposed';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const PAID = 'paid';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TradeCycle::class, 'trade_cycle_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function lot(): HasOne
    {
        return $this->hasOne(InventoryLot::class);
    }
}
