<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'status', 'requested_by', 'requested_at', 'notice_ends_on', 'reason', 'original_maturity_date',
    'cancelled_at', 'cancelled_by', 'cancel_note', 'matured_at', 'matured_by', 'completed_at',
])]
class WithdrawalRequest extends Model
{
    public const REQUESTED = 'requested';

    public const CANCELLED = 'cancelled';

    public const MATURED = 'matured';

    public const COMPLETED = 'completed';

    public const LABELS = [
        self::REQUESTED => 'Notice running',
        self::MATURED => 'Matured early, payout in progress',
        self::COMPLETED => 'Capital returned',
        self::CANCELLED => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'notice_ends_on' => 'date', 'original_maturity_date' => 'date',
            'cancelled_at' => 'datetime', 'matured_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function isOpen(): bool
    {
        return $this->status === self::REQUESTED;
    }

    /** Has the notice period run its course? */
    public function noticeElapsed(): bool
    {
        return $this->notice_ends_on->lte(now()->startOfDay());
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
