<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'is_active'])]
class Commodity extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
