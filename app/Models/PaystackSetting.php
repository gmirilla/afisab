<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_key', 'secret_key', 'enabled', 'last_tested_at', 'last_test_ok', 'last_test_message', 'updated_by'])]
#[Hidden(['public_key', 'secret_key'])]
class PaystackSetting extends Model
{
    protected function casts(): array
    {
        return [
            'public_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'enabled' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }
}
