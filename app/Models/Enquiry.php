<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'type', 'name', 'company', 'email', 'phone', 'commodity', 'quantity', 'location', 'message', 'consented_at',
    'status', 'handled_by', 'handled_at', 'internal_note', 'supplier_id',
])]
class Enquiry extends Model
{
    public const QUOTE = 'quote';

    public const SUPPLIER = 'supplier';

    public const GENERAL = 'general';

    public const TYPES = [self::QUOTE => 'Quote request', self::SUPPLIER => 'Supplier interest', self::GENERAL => 'General enquiry'];

    public const NEW = 'new';

    public const CONTACTED = 'contacted';

    public const CLOSED = 'closed';

    public const STATUSES = [self::NEW => 'New', self::CONTACTED => 'Contacted', self::CLOSED => 'Closed'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
