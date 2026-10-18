<?php

namespace App\Services\Trading;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\CycleAllocation;
use App\Models\CycleExpense;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TradeCycle;
use App\Models\User;
use App\Notifications\CycleAwaitingApproval;
use App\Services\Ledger\LedgerService;
use App\Services\Performance\PerformanceService;
use App\Services\Risk\RiskService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A trading cycle buys one commodity with allocated investor capital, stores it, sells it and closes.
 *
 * Money flow (see docs/ACCOUNTING.md):
 *   purchase paid      Dr Inventory           / Cr Main Operations
 *   expense paid       Dr Trading Expenses    / Cr Main Operations
 *   buyer pays         Dr Investors Profit    / Cr Inventory (cost) + Cr Trading Income (margin)
 *   cycle closes       Dr Main Operations     / Cr Investors Profit   (sales proceeds less the 20% retention)
 * and the cycle's net profit is shared across the allocated policies for the monthly distribution.
 */
class CycleService
{
    public function __construct(private LedgerService $ledger, private PerformanceService $performance, private RiskService $risk) {}

    public const CAPITAL_NONE = 'none';

    public const CAPITAL_ALL = 'all';

    public const CAPITAL_TARGET = 'target';

    /**
     * Propose a cycle. It starts PENDING: investor capital is reserved but nothing can be bought until a Director
     * approves it (approve()). Capital can be allocated by hand afterwards or, here, taken from the free pool:
     *
     *   none    open it empty
     *   all     every eligible policy's whole free capital
     *   target  $target kobo, taken from the eligible policies pro rata to their free capital
     *
     * Policies maturing within $bufferDays (default: config) are left out unless $includeMaturing. All or nothing:
     * if any rule (risk limits, free capital) fails, no cycle is created.
     */
    public function open(string $name, Commodity $commodity, ?string $notes, User $by, string $capital = self::CAPITAL_NONE, ?int $target = null, ?int $bufferDays = null, bool $includeMaturing = false): TradeCycle
    {
        if (! $commodity->is_active) {
            throw new BusinessRuleException('That commodity is not active.');
        }
        if (! in_array($capital, [self::CAPITAL_NONE, self::CAPITAL_ALL, self::CAPITAL_TARGET], true)) {
            throw new BusinessRuleException('Unknown capital option.');
        }

        $cycle = DB::transaction(function () use ($name, $commodity, $notes, $by, $capital, $target, $bufferDays, $includeMaturing) {
            $cycle = TradeCycle::create([
                'reference' => 'CYC-'.now()->format('ym').'-'.strtoupper(Str::random(4)),
                'name' => $name,
                'commodity_id' => $commodity->id,
                'status' => TradeCycle::PENDING,
                'notes' => $notes,
                'opened_by' => $by->id,
                'opened_at' => now(),
            ]);

            if ($capital !== self::CAPITAL_NONE) {
                $plan = $this->capitalPlan($capital, $target, $bufferDays, $includeMaturing);
                if ($plan['rows'] === []) {
                    throw new BusinessRuleException('There is no free capital to allocate'.($plan['excluded'] ? ' (policies maturing soon were left out; tick "include them anyway" to use them)' : '').'.');
                }
                foreach ($plan['rows'] as $row) {
                    $this->allocate($cycle, $row['investment'], $row['take'], $by);
                }
            }

            activity()->performedOn($cycle)->causedBy($by)->withProperties(['capital' => $capital, 'target' => $target, 'allocated' => $cycle->budget()])->log('Trading cycle proposed');

            return $cycle;
        });

        foreach (User::role('super-admin')->active()->get()->reject(fn (User $u) => $u->id === $by->id) as $director) {
            $director->notify(new CycleAwaitingApproval($cycle));
        }

        return $cycle;
    }

    /**
     * What taking free capital would look like right now (also drives the preview on the form).
     *
     * @return array{rows: list<array{investment: Investment, free: int, take: int}>, excluded: list<array{investment: Investment, free: int}>, total: int, buffer_days: int}
     */
    public function capitalPlan(string $mode, ?int $target = null, ?int $bufferDays = null, bool $includeMaturing = false): array
    {
        $buffer = $bufferDays ?? (int) config('afisab.cycle_maturity_buffer_days');
        $cutoff = now()->startOfDay()->addDays($buffer);

        $eligible = $excluded = [];
        foreach (Investment::with('user')->where('status', InvestmentStatus::Active)->whereDoesntHave('withdrawalRequests', fn ($q) => $q->where('status', 'requested'))->orderBy('id')->get() as $investment) {
            $free = $this->unallocated($investment);
            if ($free <= 0) {
                continue;
            }
            if (! $includeMaturing && $investment->maturity_date !== null && $investment->maturity_date->lte($cutoff)) {
                $excluded[] = ['investment' => $investment, 'free' => $free];

                continue;
            }
            $eligible[] = ['investment' => $investment, 'free' => $free];
        }

        $total = (int) array_sum(array_column($eligible, 'free'));
        $takes = array_column($eligible, 'free');
        if ($mode === self::CAPITAL_TARGET) {
            if ($target === null || $target <= 0) {
                throw new BusinessRuleException('Enter the amount to take from free capital.');
            }
            if ($target > $total) {
                throw new BusinessRuleException('Only '.Money::format($total).' of eligible free capital is available; you asked for '.Money::format($target).'.');
            }
            $takes = $this->proRata($target, $takes);
        }

        $rows = [];
        foreach ($eligible as $i => $row) {
            if ($takes[$i] > 0) {
                $rows[] = $row + ['take' => $takes[$i]];
            }
        }

        return ['rows' => $rows, 'excluded' => $excluded, 'total' => $total, 'buffer_days' => $buffer];
    }

    /**
     * A warning (never a block) when the money still to be spent on cycles exceeds the cash in Main Operations.
     * Budgets are measured on full principal but Main Operations holds about 95% of it (the rest is the Risk Fund).
     */
    public function cashWarning(): ?string
    {
        $toSpend = 0;
        foreach (TradeCycle::whereIn('status', TradeCycle::HOLDING)->get() as $cycle) {
            $paid = (int) $cycle->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->sum('total');
            $toSpend += max(0, $cycle->budget() - $paid);
        }
        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        if ($toSpend <= $cash) {
            return null;
        }

        return 'Budgets of open and pending cycles still to be spent total '.Money::format($toSpend).', but Main Operations holds '.Money::format($cash)
            .' (about 95% of principal; the other 5% is in the Risk Fund). Purchases will be blocked when the cash runs out.';
    }

    /** Director approves a pending cycle (never the person who proposed it): it opens and can trade. */
    public function approve(TradeCycle $cycle, User $by): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();

            if (! $cycle->isPending()) {
                throw new BusinessRuleException('Only a cycle awaiting approval can be approved.');
            }
            if ($cycle->opened_by === $by->id) {
                throw new BusinessRuleException('A cycle must be approved by someone other than the person who proposed it.');
            }
            if ($cycle->allocations()->doesntExist()) {
                throw new BusinessRuleException('This cycle has no investor capital allocated, so there is nothing to approve.');
            }
            foreach ($cycle->allocations()->with('investment')->get() as $allocation) {
                $investment = Investment::whereKey($allocation->investment_id)->lockForUpdate()->firstOrFail();
                if ($investment->status !== InvestmentStatus::Active) {
                    throw new BusinessRuleException("Policy {$investment->reference} is no longer active; remove its allocation first.");
                }
                if ($allocation->amount > $this->unallocated($investment, $cycle)) {
                    throw new BusinessRuleException("Policy {$investment->reference} no longer has that much free capital; adjust its allocation first.");
                }
            }
            $this->risk->assertCycleBudget($cycle->budget());

            $cycle->update(['status' => TradeCycle::OPEN, 'approved_by' => $by->id, 'approved_at' => now()]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['budget' => $cycle->budget()])->log('Trading cycle approved');

            return $cycle;
        });
    }

    /** Director declines a pending cycle; its capital is released at once. */
    public function reject(TradeCycle $cycle, User $by, string $note): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by, $note) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();

            if (! $cycle->isPending()) {
                throw new BusinessRuleException('Only a cycle awaiting approval can be rejected.');
            }
            $cycle->update(['status' => TradeCycle::CANCELLED, 'approved_by' => $by->id, 'approved_at' => now(), 'decision_note' => $note, 'closed_by' => $by->id, 'closed_at' => now()]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['note' => $note])->log('Trading cycle rejected');

            return $cycle;
        });
    }

    /** Split $total in proportion to $weights, exactly (largest remainder). */
    private function proRata(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $shares = $remainders = [];
        foreach ($weights as $i => $weight) {
            $exact = $total * $weight;
            $shares[$i] = intdiv($exact, $sum);
            $remainders[$i] = $exact % $sum;
        }
        $left = $total - array_sum($shares);
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $left) as $i) {
            $shares[$i]++;
        }

        return $shares;
    }

    /** Capital of a policy not already held by another pending or open cycle. */
    public function unallocated(Investment $investment, ?TradeCycle $except = null): int
    {
        $used = CycleAllocation::where('investment_id', $investment->id)
            ->whereHas('cycle', fn ($q) => $q->whereIn('status', TradeCycle::HOLDING))
            ->when($except, fn ($q) => $q->where('trade_cycle_id', '!=', $except->id))
            ->sum('amount');

        return $investment->amount - (int) $used;
    }

    /** Allocate (or change the allocation of) a policy's capital to a cycle. */
    public function allocate(TradeCycle $cycle, Investment $investment, int $amount, User $by): CycleAllocation
    {
        return DB::transaction(function () use ($cycle, $investment, $amount, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();
            $this->assertLive($cycle);

            if ($investment->status !== InvestmentStatus::Active) {
                throw new BusinessRuleException('Only active investments can be allocated to a cycle.');
            }
            if ($investment->openWithdrawal()) {
                throw new BusinessRuleException('This investment has given notice of early withdrawal, so its capital cannot be put into a trading cycle.');
            }
            if ($amount <= 0) {
                throw new BusinessRuleException('The allocation must be greater than zero.');
            }
            $available = $this->unallocated($investment, $cycle);
            if ($amount > $available) {
                throw new BusinessRuleException('Only '.Money::format($available).' of this investment is free to allocate.');
            }

            $existing = $cycle->allocations()->where('investment_id', $investment->id)->first();
            if ($cycle->isOpen() && $amount > ($existing?->amount ?? 0) && ! $by->can('cycles.approve')) {
                // Otherwise a cycle could be approved small and then filled up without anyone senior looking again.
                throw new BusinessRuleException('Capital can only be added to an approved cycle by a Director. Propose a new cycle for more capital.');
            }
            $newBudget = $cycle->budget() - ($existing?->amount ?? 0) + $amount;
            if ($newBudget < $cycle->committed()) {
                throw new BusinessRuleException('That would take the cycle budget below what is already committed to purchases.');
            }

            $this->risk->assertCycleBudget($newBudget);
            $this->risk->assertDeployment($amount - ($existing?->amount ?? 0));

            $allocation = $cycle->allocations()->updateOrCreate(['investment_id' => $investment->id], ['amount' => $amount]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['investment' => $investment->reference, 'amount' => $amount])->log('Capital allocated to cycle');

            return $allocation;
        });
    }

    public function removeAllocation(CycleAllocation $allocation, User $by): void
    {
        DB::transaction(function () use ($allocation, $by) {
            $cycle = TradeCycle::whereKey($allocation->trade_cycle_id)->lockForUpdate()->firstOrFail();
            $this->assertLive($cycle);

            if ($cycle->budget() - $allocation->amount < $cycle->committed()) {
                throw new BusinessRuleException('Removing this allocation would take the budget below what is already committed to purchases.');
            }
            $allocation->delete();
            activity()->performedOn($cycle)->causedBy($by)->log('Allocation removed from cycle');
        });
    }

    /** Cancel a cycle (pending or open) in which no money has moved. */
    public function cancel(TradeCycle $cycle, User $by): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertLive($cycle);

            $moved = $cycle->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->exists()
                || $cycle->expenses()->where('status', CycleExpense::PAID)->exists()
                || $cycle->sales()->where('status', Sale::PAID)->exists();
            if ($moved) {
                throw new BusinessRuleException('Money has already moved in this cycle; it must be closed, not cancelled.');
            }

            $cycle->purchases()->whereIn('status', [Purchase::PROPOSED, Purchase::APPROVED])->update(['status' => Purchase::CANCELLED]);
            $cycle->expenses()->where('status', CycleExpense::PROPOSED)->update(['status' => CycleExpense::REJECTED]);
            $cycle->update(['status' => TradeCycle::CANCELLED, 'closed_by' => $by->id, 'closed_at' => now()]);
            activity()->performedOn($cycle)->causedBy($by)->log('Trading cycle cancelled');

            return $cycle;
        });
    }

    /**
     * Close the cycle: settle the proceeds, work out the profit and hand the result to the monthly distribution.
     * Everything must be finished first (no unpaid items, all stock sold).
     */
    public function close(TradeCycle $cycle, User $by): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($cycle);

            $this->assertReadyToClose($cycle);

            $purchases = (int) $cycle->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->sum('total');
            $expenses = (int) $cycle->expenses()->where('status', CycleExpense::PAID)->sum('amount');
            $sales = (int) $cycle->sales()->where('status', Sale::PAID)->sum('total');
            $net = $sales - $purchases - $expenses;

            $retained = $net > 0 ? LedgerService::bps($net, (int) config('afisab.profit_retention_bps')) : 0;
            $returned = $sales - $retained;
            if ($returned > 0) {
                $this->ledger->transfer(
                    LedgerAccount::INVESTOR_PROFIT,
                    LedgerAccount::MAIN_OPS,
                    $returned,
                    "Cycle {$cycle->reference} settled: capital, expenses and profit balance returned",
                    $cycle,
                    "CYC-{$cycle->reference}-SETTLE",
                    $by->id,
                );
            }

            $allocations = $cycle->allocations()->pluck('amount', 'investment_id')->all();
            $this->performance->recordCycleResult($cycle->reference, $allocations, $net);

            $cycle->update([
                'status' => TradeCycle::CLOSED, 'closed_by' => $by->id, 'closed_at' => now(),
                'total_purchases' => $purchases, 'total_expenses' => $expenses, 'total_sales' => $sales,
                'net_profit' => $net, 'retained' => $retained,
            ]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['net_profit' => $net])->log('Trading cycle closed');

            return $cycle;
        });
    }

    private function assertReadyToClose(TradeCycle $cycle): void
    {
        $problems = [];

        if ($cycle->allocations()->doesntExist()) {
            $problems[] = 'no investor capital was allocated';
        }
        if ($cycle->purchases()->whereIn('status', [Purchase::PROPOSED, Purchase::APPROVED])->exists()) {
            $problems[] = 'there are purchases not yet paid (pay, reject or cancel them)';
        }
        if ($cycle->purchases()->where('status', Purchase::PAID)->exists()) {
            $problems[] = 'there are paid purchases whose goods have not been received';
        }
        if ($cycle->expenses()->where('status', CycleExpense::PROPOSED)->exists()) {
            $problems[] = 'there are expenses not yet paid or rejected';
        }
        if ($cycle->sales()->whereIn('status', [Sale::DRAFT, Sale::CONFIRMED])->exists()) {
            $problems[] = 'there are sales not yet paid or cancelled';
        }
        if ($cycle->lots()->where('remaining_kg', '>', 0)->exists()) {
            $problems[] = 'unsold stock remains in the warehouse';
        }
        if ($cycle->sales()->where('status', Sale::PAID)->doesntExist()) {
            $problems[] = 'nothing has been sold';
        }

        if ($problems) {
            throw new BusinessRuleException('This cycle cannot be closed yet: '.implode('; ', $problems).'.');
        }
    }

    private function assertOpen(TradeCycle $cycle): void
    {
        if ($cycle->isPending()) {
            throw new BusinessRuleException('This trading cycle is waiting for a Director to approve it.');
        }
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
    }

    /** Pending or open: capital can still be changed and the cycle can still be cancelled. */
    private function assertLive(TradeCycle $cycle): void
    {
        if (! in_array($cycle->status, TradeCycle::HOLDING, true)) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
    }
}
