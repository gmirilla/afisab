<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public const MAX_ITEMS = 5;

    public function items(): HasMany
    {
        return $this->hasMany(EnquiryItem::class)->orderBy('position');
    }

    /**
     * The commodities as one short phrase for lists and subjects: "Maize", "Maize, Sorghum", "Maize, Sorghum +1".
     * Falls back to the single legacy column for an enquiry that has no items.
     */
    public function commoditySummary(int $show = 2): string
    {
        $names = $this->items->pluck('commodity')->all() ?: array_filter([$this->commodity]);
        if ($names === []) {
            return '';
        }

        return implode(', ', array_slice($names, 0, $show)).(count($names) > $show ? ' +'.(count($names) - $show) : '');
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
