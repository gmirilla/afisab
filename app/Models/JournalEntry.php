<?php

namespace App\Models;

use App\Exceptions\LedgerException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['reference', 'description', 'posted_at', 'posted_by'])]
class JournalEntry extends Model
{
    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // The ledger is append-only: corrections are made with reversing entries.
        static::updating(fn () => throw new LedgerException('Journal entries are immutable.'));
        static::deleting(fn () => throw new LedgerException('Journal entries cannot be deleted.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
