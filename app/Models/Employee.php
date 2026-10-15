<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'email', 'phone', 'position', 'department', 'hire_date', 'basic_salary',
    'bank_name', 'account_number', 'account_name', 'tax_id', 'is_active',
])]
class Employee extends Model
{
    protected function casts(): array
    {
        return ['hire_date' => 'date', 'basic_salary' => 'integer', 'is_active' => 'boolean'];
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
