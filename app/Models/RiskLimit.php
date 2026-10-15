<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'is_active', 'updated_by'])]
class RiskLimit extends Model
{
    protected function casts(): array
    {
        return ['value' => 'integer', 'is_active' => 'boolean'];
    }
}
