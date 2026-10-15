<?php

namespace App\Services\Treasury;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BankReconciliation;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Read-side view of the company's funds, plus the cash-sufficiency guard used before any payout. */
class TreasuryService
{
    public const DUE_WINDOW_DAYS = 30;

    public function __construct(private LedgerService $ledger) {}

    /**
     * Headline numbers. "Available" is what Main Operations holds beyond what is already owed
     * within the next 30 days (principal maturing + approved profit not yet paid).
     *
     * @return array<string, int>
     */
    public function overview(): array
    {
        $mainOps = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        $profitPayable = $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE);
        $principalDue = $this->principalDue(self::DUE_WINDOW_DAYS);

        return [
            'main_ops' => $mainOps,
            'risk_fund' => $this->ledger->balance(LedgerAccount::RISK_FUND),
            'investor_profit_account' => $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT),
            'domiciliary' => $this->ledger->balance(LedgerAccount::DOMICILIARY),
            'investor_capital_held' => $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS),
            'profit_payable' => $profitPayable,
            'principal_due' => $principalDue,
            'obligations_due' => $principalDue + $profitPayable,
            'available' => $mainOps - $principalDue - $profitPayable,
        ];
    }

    /** Principal that has matured, or matures within $days, and has not been paid out. */
    public function principalDue(int $days): int
    {
        return (int) Investment::whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])
            ->whereDate('maturity_date', '<=', now()->startOfDay()->addDays($days))
            ->sum('amount');
    }

    /**
     * Per-policy funds position: what is held, where it sits, and what is due.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function policyRows(): Collection
    {
        $held = $this->ledger->balancesBySubject(LedgerAccount::INVESTOR_FUNDS, 'investment');
        $cash = $this->ledger->balancesBySubject(LedgerAccount::MAIN_OPS, 'investment');
        $risk = $this->ledger->balancesBySubject(LedgerAccount::RISK_FUND, 'investment');
        $payable = $this->ledger->balancesBySubject(LedgerAccount::INVESTOR_PROFIT_PAYABLE, 'investment');
        $horizon = now()->startOfDay()->addDays(self::DUE_WINDOW_DAYS);

        return Investment::with(['user', 'plan'])
            ->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])
            ->orderBy('maturity_date')
            ->get()
            ->map(fn (Investment $i) => [
                'investment' => $i,
                'capital_held' => $held[$i->id] ?? 0,
                'in_main_ops' => $cash[$i->id] ?? 0,
                'in_risk_fund' => $risk[$i->id] ?? 0,
                'profit_payable' => $payable[$i->id] ?? 0,
                'principal_due' => $i->maturity_date && $i->maturity_date->lte($horizon) ? ($held[$i->id] ?? 0) : 0,
            ]);
    }

    /**
     * Principal falling due, grouped by month.
     *
     * @return Collection<string, array{label: string, count: int, amount: int}>
     */
    public function maturitySchedule(): Collection
    {
        return Investment::whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])
            ->orderBy('maturity_date')->get()
            ->groupBy(fn (Investment $i) => $i->maturity_date->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'count' => $group->count(),
                'amount' => (int) $group->sum('amount'),
            ]);
    }

    /** Refuse a payment the Main Operations account cannot cover. */
    public function assertCanPay(int $amount): void
    {
        $balance = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        if ($amount > $balance) {
            throw new BusinessRuleException('Insufficient funds in Main Operations: '.Money::format($balance).' available, '.Money::format($amount).' required.');
        }
    }

    /** Record a bank statement balance against the ledger as at the end of $asOf. */
    public function reconcile(LedgerAccount $account, Carbon $asOf, int $statementBalance, ?string $note, User $by): BankReconciliation
    {
        if ($asOf->isFuture()) {
            throw new BusinessRuleException('The reconciliation date cannot be in the future.');
        }

        $ledgerBalance = $this->ledger->balance($account, null, $asOf->copy()->endOfDay());

        $rec = BankReconciliation::create([
            'ledger_account_id' => $account->id,
            'as_of' => $asOf->toDateString(),
            'statement_balance' => $statementBalance,
            'ledger_balance' => $ledgerBalance,
            'difference' => $statementBalance - $ledgerBalance,
            'note' => $note,
            'reconciled_by' => $by->id,
        ]);

        activity()->performedOn($account)->causedBy($by)->withProperties([
            'as_of' => $asOf->toDateString(), 'difference' => $rec->difference,
        ])->log('Bank reconciliation recorded');

        return $rec;
    }
}
