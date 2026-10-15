<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_run_id', 'employee_id', 'basic', 'allowances', 'commission', 'deductions', 'net'])]
class PayrollLine extends Model
{
    protected function casts(): array
    {
        return ['basic' => 'integer', 'allowances' => 'integer', 'commission' => 'integer', 'deductions' => 'integer', 'net' => 'integer'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function gross(): int
    {
        return $this->basic + $this->allowances + $this->commission;
    }
}
