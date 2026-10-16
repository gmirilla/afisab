<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_run_id', 'employee_id', 'basic', 'allowances', 'commission', 'deductions', 'net',
    'housing', 'transport', 'pensionable', 'pension_employee', 'pension_employer', 'nhf', 'taxable', 'paye', 'tax_months', 'pension_administrator_id',
])]
class PayrollLine extends Model
{
    protected function casts(): array
    {
        return [
            'basic' => 'integer', 'allowances' => 'integer', 'commission' => 'integer', 'deductions' => 'integer', 'net' => 'integer',
            'housing' => 'integer', 'transport' => 'integer', 'pensionable' => 'integer', 'pension_employee' => 'integer',
            'pension_employer' => 'integer', 'nhf' => 'integer', 'taxable' => 'integer', 'paye' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(PensionAdministrator::class, 'pension_administrator_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function gross(): int
    {
        return $this->basic + $this->housing + $this->transport + $this->allowances + $this->commission;
    }
}
