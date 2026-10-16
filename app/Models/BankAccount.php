<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bank_name', 'account_number', 'account_name', 'note', 'is_active', 'updated_by'])]
class BankAccount extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
