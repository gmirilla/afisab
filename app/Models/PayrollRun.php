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
    'statutory_rule_set_id', 'total_paye', 'total_pension_employee', 'total_pension_employer', 'total_nhf',
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
            'total_paye' => 'integer', 'total_pension_employee' => 'integer', 'total_pension_employer' => 'integer', 'total_nhf' => 'integer',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class, 'statutory_rule_set_id');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(StatutoryRemittance::class);
    }

    /** True when statutory deductions were calculated for this run. */
    public function hasStatutory(): bool
    {
        return $this->statutory_rule_set_id !== null;
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
