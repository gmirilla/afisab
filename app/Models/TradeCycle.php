<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'name', 'commodity_id', 'status', 'notes', 'opened_by', 'opened_at', 'closed_by', 'closed_at',
    'total_purchases', 'total_expenses', 'total_sales', 'net_profit', 'retained',
])]
class TradeCycle extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_purchases' => 'integer',
            'total_expenses' => 'integer',
            'total_sales' => 'integer',
            'net_profit' => 'integer',
            'retained' => 'integer',
        ];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CycleAllocation::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CycleExpense::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** Capital allocated to this cycle: the most that may be committed to purchases. */
    public function budget(): int
    {
        return (int) $this->allocations()->sum('amount');
    }

    /** Purchases that are approved or beyond count against the budget. */
    public function committed(): int
    {
        return (int) $this->purchases()->whereIn('status', [Purchase::APPROVED, Purchase::PAID, Purchase::RECEIVED])->sum('total');
    }
}
