<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'period', 'type', 'description', 'amount', 'created_by'])]
class PayrollAdjustment extends Model
{
    public const TYPES = ['allowance', 'commission', 'deduction'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
