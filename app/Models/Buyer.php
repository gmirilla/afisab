<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'contact_name', 'phone', 'email', 'address', 'is_active'])]
class Buyer extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
