<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'trade_cycle_id', 'buyer_id', 'inventory_lot_id', 'quantity_kg', 'unit_price', 'total',
    'cost_of_goods', 'status', 'created_by', 'confirmed_at', 'received_by', 'received_at', 'payment_reference', 'note',
])]
class Sale extends Model
{
    public const DRAFT = 'draft';

    public const CONFIRMED = 'confirmed';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
            'cost_of_goods' => 'integer',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TradeCycle::class, 'trade_cycle_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
