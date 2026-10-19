<?php

namespace App\Models;

use App\Exceptions\LedgerException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'payment_id', 'receipt_number', 'version', 'status', 'amount', 'received_on', 'file_path', 'sha256', 'size',
    'signatory_id', 'stamp_id', 'issued_by', 'issued_at', 'reason', 'superseded_at', 'voided_at', 'voided_by', 'void_reason',
])]
class Receipt extends Model
{
    public const ISSUED = 'issued';

    public const SUPERSEDED = 'superseded';

    public const VOID = 'void';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'received_on' => 'date', 'issued_at' => 'datetime', 'superseded_at' => 'datetime', 'voided_at' => 'datetime', 'version' => 'integer', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        // A receipt is evidence of money received: the file, number and amount never change. It can only be superseded or voided.
        static::updating(function (self $receipt) {
            foreach (['file_path', 'sha256', 'receipt_number', 'amount', 'received_on', 'issued_at', 'version', 'investment_id', 'payment_id'] as $field) {
                if ($receipt->isDirty($field)) {
                    throw new LedgerException("An issued receipt cannot change its {$field}.");
                }
            }
        });
        static::deleting(fn () => throw new LedgerException('Receipts cannot be deleted.'));
    }

    public function isCurrent(): bool
    {
        return $this->status === self::ISSUED;
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(CompanyStamp::class, 'stamp_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function filename(): string
    {
        return 'AFISAB-Receipt-'.str_replace('/', '-', $this->receipt_number).($this->version > 1 ? "-v{$this->version}" : '').'.pdf';
    }
}
