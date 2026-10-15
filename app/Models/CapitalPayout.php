<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'amount', 'status', 'prepared_by', 'approved_by', 'approved_at',
    'paid_by', 'paid_at', 'payment_reference', 'note',
])]
class CapitalPayout extends Model
{
    public const PREPARED = 'prepared';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
