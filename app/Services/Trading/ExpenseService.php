<?php

namespace App\Services\Trading;

use App\Exceptions\BusinessRuleException;
use App\Models\CycleExpense;
use App\Models\LedgerAccount;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Support\Facades\DB;

/** Transport, storage, labour and packaging costs of a cycle: proposed by one person, paid by another. */
class ExpenseService
{
    public function __construct(private LedgerService $ledger, private TreasuryService $treasury) {}

    public function propose(TradeCycle $cycle, string $category, string $description, int $amount, User $by): CycleExpense
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
        if (! in_array($category, CycleExpense::CATEGORIES, true)) {
            throw new BusinessRuleException('Unknown expense category.');
        }
        if ($amount <= 0) {
            throw new BusinessRuleException('The amount must be greater than zero.');
        }

        $expense = $cycle->expenses()->create([
            'category' => $category, 'description' => $description, 'amount' => $amount,
            'status' => CycleExpense::PROPOSED, 'proposed_by' => $by->id,
        ]);
        activity()->performedOn($expense)->causedBy($by)->log('Expense proposed');

        return $expense;
    }

    public function pay(CycleExpense $expense, string $reference, User $by): CycleExpense
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($expense, $reference, $by) {
            $expense = CycleExpense::whereKey($expense->id)->lockForUpdate()->with('cycle')->firstOrFail();

            if ($expense->status !== CycleExpense::PROPOSED) {
                throw new BusinessRuleException('Only a proposed expense can be paid.');
            }
            if ($expense->proposed_by === $by->id) {
                throw new BusinessRuleException('The person who proposed an expense cannot also pay it.');
            }
            if (! $expense->cycle->isOpen()) {
                throw new BusinessRuleException('This trading cycle is no longer open.');
            }
            $this->treasury->assertCanPay($expense->amount);

            $this->ledger->post(
                "Cycle expense ({$expense->category}): {$expense->description}",
                [
                    ['account' => LedgerAccount::TRADING_EXPENSES, 'direction' => 'debit', 'amount' => $expense->amount, 'subject' => $expense->cycle],
                    ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'credit', 'amount' => $expense->amount, 'subject' => $expense->cycle],
                ],
                "EXP-{$expense->id}-PAY",
                $expense->cycle,
                $by->id,
            );

            $expense->update(['status' => CycleExpense::PAID, 'paid_by' => $by->id, 'paid_at' => now(), 'payment_reference' => $reference]);
            activity()->performedOn($expense)->causedBy($by)->withProperties(['reference' => $reference])->log('Expense paid');

            return $expense;
        });
    }

    public function reject(CycleExpense $expense, User $by): CycleExpense
    {
        if ($expense->status !== CycleExpense::PROPOSED) {
            throw new BusinessRuleException('Only a proposed expense can be rejected.');
        }

        $expense->update(['status' => CycleExpense::REJECTED]);
        activity()->performedOn($expense)->causedBy($by)->log('Expense rejected');

        return $expense;
    }
}
