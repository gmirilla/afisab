<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ledger_account_id', 'as_of', 'statement_balance', 'ledger_balance', 'difference', 'note', 'reconciled_by'])]
class BankReconciliation extends Model
{
    protected function casts(): array
    {
        return ['as_of' => 'date', 'statement_balance' => 'integer', 'ledger_balance' => 'integer', 'difference' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isBalanced(): bool
    {
        return $this->difference === 0;
    }
}
