<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'version', 'status', 'template_version', 'policy_number', 'file_path', 'sha256', 'size',
    'signatory_id', 'issued_by', 'issued_at', 'reason', 'superseded_at',
])]
class PolicyDocument extends Model
{
    public const ISSUED = 'issued';

    public const SUPERSEDED = 'superseded';

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'superseded_at' => 'datetime', 'size' => 'integer', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        // An issued agreement is a record: its file and hash can never be rewritten, only superseded.
        static::updating(function (self $doc) {
            foreach (['file_path', 'sha256', 'policy_number', 'template_version', 'issued_at', 'version', 'investment_id'] as $field) {
                if ($doc->isDirty($field)) {
                    throw new \App\Exceptions\LedgerException("An issued policy document cannot change its {$field}.");
                }
            }
        });
        static::deleting(fn () => throw new \App\Exceptions\LedgerException('Policy documents cannot be deleted.'));
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function filename(): string
    {
        return 'AFISAB-Policy-'.str_replace('/', '-', $this->policy_number).($this->version > 1 ? "-v{$this->version}" : '').'.pdf';
    }
}
