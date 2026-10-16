<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_run_id', 'kind', 'pension_administrator_id', 'payee', 'amount', 'due_date', 'status', 'remitted_by', 'remitted_at', 'reference'])]
class StatutoryRemittance extends Model
{
    public const DUE = 'due';

    public const REMITTED = 'remitted';

    public const PAYE = 'paye';

    public const PENSION = 'pension';

    public const NHF = 'nhf';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'due_date' => 'date', 'remitted_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(PensionAdministrator::class, 'pension_administrator_id');
    }

    public function remitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitted_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::DUE && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::PAYE => 'PAYE', self::PENSION => 'Pension', default => 'NHF',
        };
    }
}
