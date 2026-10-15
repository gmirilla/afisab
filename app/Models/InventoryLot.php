<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trade_cycle_id', 'purchase_id', 'commodity_id', 'warehouse', 'quantity_kg', 'remaining_kg', 'cost_total',
    'remaining_cost', 'grade', 'moisture_percent', 'qa_passed', 'qa_notes', 'received_by', 'received_at',
])]
class InventoryLot extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'integer',
            'remaining_kg' => 'integer',
            'cost_total' => 'integer',
            'remaining_cost' => 'integer',
            'qa_passed' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TradeCycle::class, 'trade_cycle_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}
