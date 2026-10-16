<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'effective_from', 'status', 'rules', 'note', 'activated_by', 'activated_at', 'updated_by'])]
class StatutoryRuleSet extends Model
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'rules' => 'array', 'activated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // An activated set may have been used to pay staff and file returns: it can never change.
        static::updating(function (self $set) {
            if ($set->getOriginal('status') === self::ACTIVE) {
                throw new \App\Exceptions\LedgerException('An active rule set is immutable. Add a new set instead.');
            }
        });
        static::deleting(function (self $set) {
            if ($set->status === self::ACTIVE) {
                throw new \App\Exceptions\LedgerException('An active rule set cannot be deleted.');
            }
        });
    }

    /** The set that governs a payroll month (YYYY-MM): active, latest effective_from not after the month's first day. */
    public static function forPeriod(string $period): ?self
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        return self::where('status', self::ACTIVE)->where('effective_from', '<=', $start)->orderByDesc('effective_from')->orderByDesc('id')->first();
    }
}
