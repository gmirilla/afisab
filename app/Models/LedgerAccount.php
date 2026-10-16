<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'currency', 'is_system', 'description'])]
class LedgerAccount extends Model
{
    public const MAIN_OPS = 'MAIN_OPS';

    public const RISK_FUND = 'RISK_FUND';

    public const INVESTOR_PROFIT = 'INVESTOR_PROFIT';

    public const DOMICILIARY = 'DOMICILIARY';

    public const INVESTOR_FUNDS = 'INVESTOR_FUNDS';

    public const INVENTORY = 'INVENTORY';

    public const TRADING_INCOME = 'TRADING_INCOME';

    public const INVESTOR_PROFIT_PAYABLE = 'INVESTOR_PROFIT_PAYABLE';

    public const TRADING_EXPENSES = 'TRADING_EXPENSES';

    public const INSURANCE_EXPENSE = 'INSURANCE_EXPENSE';

    public const PAYROLL_EXPENSE = 'PAYROLL_EXPENSE';

    public const STATUTORY_PAYABLE = 'STATUTORY_PAYABLE';

    public const PAYE_PAYABLE = 'PAYE_PAYABLE';

    public const PENSION_PAYABLE = 'PENSION_PAYABLE';

    public const NHF_PAYABLE = 'NHF_PAYABLE';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    /** Debit-normal accounts (assets, expenses) grow with debits; the rest with credits. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
