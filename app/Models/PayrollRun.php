<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'period', 'status', 'total_gross', 'total_deductions', 'total_net', 'prepared_by', 'approved_by',
    'approved_at', 'paid_by', 'paid_at', 'payment_reference',
])]
class PayrollRun extends Model
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    protected function casts(): array
    {
        return [
            'total_gross' => 'integer', 'total_deductions' => 'integer', 'total_net' => 'integer',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function label(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
    }
}
