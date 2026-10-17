<?php

namespace App\Services\Reports;

use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TradeCycle;
use App\Services\Ledger\LedgerService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Read-only management reports. Each returns plain arrays so the same data feeds the screen and the CSV. */
class ReportService
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * One row per investment policy.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function investors(?string $status = null): Collection
    {
        $lines = DistributionLine::whereIn('status', [DistributionLine::PAID, DistributionLine::PAYABLE])->get()->groupBy('investment_id');

        return Investment::with(['user', 'plan'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('reference')->get()
            ->map(fn (Investment $i) => [
                'reference' => $i->reference,
                'investor' => $i->user->name,
                'email' => $i->user->email,
                'plan' => $i->plan->name,
                'frequency' => $i->profit_frequency->label(),
                'amount' => $i->amount,
                'status' => $i->status->label(),
                'start' => $i->start_date?->toDateString(),
                'maturity' => $i->maturity_date?->toDateString(),
                'profit_paid' => (int) ($lines[$i->id] ?? collect())->where('status', DistributionLine::PAID)->sum('investor_share'),
                'profit_payable' => (int) ($lines[$i->id] ?? collect())->where('status', DistributionLine::PAYABLE)->sum('investor_share'),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function cycles(): Collection
    {
        return TradeCycle::with('commodity')->orderBy('id')->get()->map(function (TradeCycle $c) {
            $closed = $c->status === TradeCycle::CLOSED;
            $purchases = $closed ? $c->total_purchases : (int) $c->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->sum('total');
            $sales = $closed ? $c->total_sales : (int) $c->sales()->where('status', Sale::PAID)->sum('total');
            $net = $c->net_profit;

            return [
                'reference' => $c->reference,
                'name' => $c->name,
                'commodity' => $c->commodity->name,
                'status' => ucfirst($c->status),
                'budget' => $c->budget(),
                'purchases' => $purchases,
                'expenses' => $closed ? $c->total_expenses : 0,
                'sales' => $sales,
                'net_profit' => $net,
                'return_bps' => $closed && $purchases > 0 ? intdiv(($net ?? 0) * 10_000, $purchases) : null,
                'closed' => $c->closed_at?->toDateString(),
            ];
        });
    }

    /**
     * Every ledger account's balance at the end of $asOf. Debits must equal credits.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, total_debit: int, total_credit: int, balanced: bool}
     */
    public function trialBalance(CarbonInterface $asOf): array
    {
        $end = $asOf->copy()->endOfDay();
        $rows = LedgerAccount::orderBy('id')->get()->map(function (LedgerAccount $a) use ($end) {
            $t = $this->ledger->totals($a, null, $end);
            $net = $t['debits'] - $t['credits'];

            return [
                'code' => $a->code, 'name' => $a->name, 'type' => $a->type,
                'debit' => max(0, $net), 'credit' => max(0, -$net),
            ];
        });

        $debit = (int) $rows->sum('debit');
        $credit = (int) $rows->sum('credit');

        return ['rows' => $rows, 'total_debit' => $debit, 'total_credit' => $credit, 'balanced' => $debit === $credit];
    }

    /**
     * Company profit and loss for a period, after the investors' profit share.
     *
     * @return array{income: array<int, array<string, mixed>>, expenses: array<int, array<string, mixed>>, total_income: int, total_expenses: int, net: int}
     */
    public function profitAndLoss(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $income = collect([LedgerAccount::TRADING_INCOME])->map(function ($code) use ($start, $end) {
            $t = $this->ledger->totals($code, $start, $end);

            return ['name' => LedgerAccount::byCode($code)->name.' (after investor profit share)', 'amount' => $t['credits'] - $t['debits']];
        })->all();

        $expenses = collect([LedgerAccount::TRADING_EXPENSES, LedgerAccount::PAYROLL_EXPENSE, LedgerAccount::INSURANCE_EXPENSE])->map(function ($code) use ($start, $end) {
            $t = $this->ledger->totals($code, $start, $end);

            return ['name' => LedgerAccount::byCode($code)->name, 'amount' => $t['debits'] - $t['credits']];
        })->all();

        $totalIncome = array_sum(array_column($income, 'amount'));
        $totalExpenses = array_sum(array_column($expenses, 'amount'));

        return ['income' => $income, 'expenses' => $expenses, 'total_income' => $totalIncome, 'total_expenses' => $totalExpenses, 'net' => $totalIncome - $totalExpenses];
    }

    /** @return Collection<int, DistributionRun> */
    public function distributions(): Collection
    {
        return DistributionRun::orderByDesc('period')->get();
    }
}
