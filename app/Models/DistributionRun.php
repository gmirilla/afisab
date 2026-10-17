<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'period', 'cutoff_date', 'pay_by', 'status', 'total_net_profit', 'total_retained', 'total_investor',
    'total_company', 'policies_deferred', 'calculated_by', 'calculated_at', 'approved_by', 'approved_at', 'paid_at', 'last_reminder_at',
])]
class DistributionRun extends Model
{
    public const CALCULATED = 'calculated';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'pay_by' => 'date',
            'total_net_profit' => 'integer',
            'total_retained' => 'integer',
            'total_investor' => 'integer',
            'total_company' => 'integer',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_reminder_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DistributionLine::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function label(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
    }

    public function isOverdue(): bool
    {
        return $this->status !== self::PAID && now()->startOfDay()->gt($this->pay_by);
    }
}
