<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['investment_id', 'cycle_reference', 'capital_allocated', 'net_profit', 'distribution_run_id', 'recorded_at'])]
class PerformanceEntry extends Model
{
    protected function casts(): array
    {
        return [
            'capital_allocated' => 'integer',
            'net_profit' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DistributionRun::class, 'distribution_run_id');
    }
}
