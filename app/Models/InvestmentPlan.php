<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'duration_months', 'minimum_amount', 'description', 'is_active', 'sort_order'])]
class InvestmentPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'minimum_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}
