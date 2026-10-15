<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trade_cycle_id', 'investment_id', 'amount'])]
class CycleAllocation extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TradeCycle::class, 'trade_cycle_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
