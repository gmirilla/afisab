<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'email', 'phone', 'position', 'department', 'hire_date', 'basic_salary',
    'bank_name', 'account_number', 'account_name', 'tax_id', 'is_active',
    'housing_allowance', 'transport_allowance', 'pension_administrator_id', 'rsa_pin', 'pension_exempt',
    'nhf_applies', 'nhf_number', 'paye_exempt', 'annual_rent', 'tax_state',
])]
class Employee extends Model
{
    protected function casts(): array
    {
        return [
            'hire_date' => 'date', 'basic_salary' => 'integer', 'is_active' => 'boolean',
            'housing_allowance' => 'integer', 'transport_allowance' => 'integer', 'annual_rent' => 'integer',
            'pension_exempt' => 'boolean', 'nhf_applies' => 'boolean', 'paye_exempt' => 'boolean',
        ];
    }

    public function pensionAdministrator(): BelongsTo
    {
        return $this->belongsTo(PensionAdministrator::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
