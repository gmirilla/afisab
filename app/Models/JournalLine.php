<?php

namespace App\Models;

use App\Exceptions\LedgerException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['journal_entry_id', 'ledger_account_id', 'direction', 'amount', 'subject_type', 'subject_id'])]
class JournalLine extends Model
{
    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LedgerException('Journal lines are immutable.'));
        static::deleting(fn () => throw new LedgerException('Journal lines cannot be deleted.'));
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
