<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distribution_run_id', 'investment_id', 'net_profit', 'retained', 'distributable', 'investor_share',
    'company_share', 'status', 'paid_at', 'payment_reference', 'paid_by', 'frequency', 'covers_from', 'covers_to',
])]
class DistributionLine extends Model
{
    public const PENDING = 'pending';   // run not yet approved

    public const PAYABLE = 'payable';   // approved, owed to the investor

    public const PAID = 'paid';

    public const NONE = 'none';         // nothing to pay (no profit that month)

    protected function casts(): array
    {
        return [
            'net_profit' => 'integer',
            'retained' => 'integer',
            'distributable' => 'integer',
            'investor_share' => 'integer',
            'company_share' => 'integer',
            'paid_at' => 'datetime',
            'covers_from' => 'date',
            'covers_to' => 'date',
        ];
    }

    /** "1 Jul - 27 Sep 2026" for a payment that covers more than one month; null for an ordinary monthly share. */
    public function coverage(): ?string
    {
        if (! $this->covers_from || ! $this->covers_to || $this->covers_from->format('Y-m') === $this->covers_to->format('Y-m')) {
            return null;
        }

        return $this->covers_from->format('j M').' - '.$this->covers_to->format('j M Y');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DistributionRun::class, 'distribution_run_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
